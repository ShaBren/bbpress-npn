<?php
/**
 * Plugin Name: New Post Notification
 * Plugin Description: Sends a notification email to subscribed members when there is a new post.
 * Author: Stephen Bryant <stephen@stephenbryant.net>
 * Website: http://www.github.com/ShaBren/bbpress-npn
 *
 * Version: 1.01
 *
 * Changelog:
 *	
 *	1.01:
 *		Fixed inability for user to disable notifications.
 */


/*
 * Based on a mod by Olaf Lederer: http://www.finalwebsites.com/portal
*/

include( 'Mail.php' );
 
function npn_get_users() 
{
	global $bbdb;
	$sql = "
		SELECT u.ID, u.user_email 
		FROM $bbdb->users AS u, $bbdb->usermeta AS um 
		WHERE u.ID = um.user_id
		AND um.meta_key = 'npn_active'
		AND um.meta_value = 1
		AND u.user_status = 0
	";
	$npn_users = $bbdb->get_results( $sql );
	
	return $npn_users;
}

function npn_is_activated( $user_id ) 
{
	$user = bb_get_user( $user_id );
	if ( $user->npn_active == 1 ) 
	{
		return true;
	}
	else 
	{
		return false;
	}
}
 
function npn_new_post() 
{
	global $bbdb, $topic_id, $bb_current_user;
	
	$npn_users = npn_get_users();
	
	$topic = get_topic( $topic_id );

	$bcc = "";

	$private_forums_options = bb_get_option( 'private_forums_options' );
	$private_forums = $private_forums_options[ 'private_forums' ];
	$required_role = $private_forums[ $topic->forum_id ];

	switch ( $required_role )
	{
		case 'OPEN':
		case 'MEMBER':
		{
			foreach ( $npn_users as $userdata ) 
			{
				if ( $bb_current_user->ID != $userdata->ID )
				{
					$bcc .= $userdata->user_email . ",";
				}
			} 
		}
		break;

		case 'MODERATOR':
		{
			foreach ( $npn_users as $userdata ) 
			{
				$user = new BP_User( $userdata->ID );
				if ( $user->has_cap( 'moderate' ) && $bb_current_user->ID != $userdata->ID )
				{
					$bcc .= $userdata->user_email . ",";
				}
			} 
		}

		case 'ADMINISTRATOR':
		{
			foreach ( $npn_users as $userdata ) 
			{
				$user = new BP_User( $userdata->ID );
				if ( $user->has_cap( 'administrate' ) && $bb_current_user->ID != $userdata->ID )
				{
					$bcc .= $userdata->user_email . ",";
				}
			} 
		}
	}

	if ( empty( $bcc ) )
	{
		return;
	}
	
	$header = array();
	$header[ 'From' ] = bb_get_option( 'from_email' ); 
	$header[ 'To' ] = "Undisclosed Recipients <" . bb_get_option( 'from_email' ) . ">"; 
	$header[ 'MIME-Version' ] = '1.0';
	$header[ 'Content-Type' ] = 'text/plain; charset="' . BBDB_CHARSET . '"';
	$header[ 'Content-Transfer-Encoding' ] = '7bit';
	$header[ 'Subject' ] = '[' . bb_get_option( 'name' ) . '] ';

	if ( $topic->topic_posts > 1 )
	{
		$header[ 'Subject' ] .= "Re: ";
	}
	
	$header[ 'Subject' ] .= $topic->topic_title;

	$posttext = bb_get_last_post( $topic_id )->post_text;

	$posttext = str_replace( array( "<br>", "<br />", "<p>" ), "\n", $posttext ); 

	$posttext = strip_tags( $posttext );
	$posttext = html_entity_decode( $posttext );

	$msg = "Posted by: " . $topic->topic_last_poster_name . "\n";
	$msg .= "To reply, go to: " . get_topic_link( $topic_id ) . "\n";
	$msg .= "----------------------------------\n";
	$msg .= $posttext;

	$mailer = Mail::factory( "sendmail" );
	$mailer->send( $bcc, $header, $msg );
}

function npn_profile() 
{
	global $user_id;
	
	if ( bb_is_user_logged_in() ) 
	{
	
		$checked = "";
		
		if ( npn_is_activated( $user_id ) ) 
		{
			$checked = ' checked="checked"';
		}
	
		echo '
			<fieldset>
				<legend>New Post Notification</legend>
				<table width="100%">
					<tr>
						<th width="21%" scope="row"><label for="npn_active">Receive new posts via email:</label></th>
						<td width="79%">
							<input name="npn_active" id="npn_active" type="checkbox" value="1"' . $checked . ' />
						</td>
					</tr>
				</table>
			</fieldset>';
	}
}

function npn_profile_edit() 
{
	global $user_id;

	if ( $_POST[ 'npn_active' ] == 1 )
	{
		bb_update_usermeta( $user_id, "npn_active", 1 );
	}
	else
	{
		bb_update_usermeta( $user_id, "npn_active", 0 );
	}
}

add_action( 'bb_new_post', 'npn_new_post' );

add_action( 'extra_profile_info', 'npn_profile' );

add_action( 'profile_edited', 'npn_profile_edit' );
?>
