<?php
/*

Plugin Name: GuRu Instagram
Description: connect to Instgram
Version: 1
Author: GuRuStu - Richard Keller
Author URI: http://gurustu.co


*/

add_action( 'admin_menu', 'guru_instagram_menu_item' );

function guru_instagram_menu_item() {
	add_options_page( 'Instagram Connect', 'Instagram Connect', 'manage_options', 'guru-instgram', 'guru_instagram_display' );
}

/** Step 3. */
function guru_instagram_display() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="wrap">';

	$guru_instagram_client_id = get_option('guru_instagram_client_id');
	$guru_instagram_client_secret = get_option('guru_instagram_client_secret');
	$guru_instagram_access_token = get_option('guru_instagram_access_token');
	$guru_instagram_user_id = get_option('guru_instagram_user_id');
	$redirect_url = admin_url() . 'options-general.php?page=guru-instgram';
	
	if( isset( $_POST['guru_instagram_client_id'] ) ) {
		update_option( 'guru_instagram_client_id', $_POST['guru_instagram_client_id'] );
		$guru_instagram_client_id = $_POST['guru_instagram_client_id'];
	}
	if( isset( $_POST['guru_instagram_client_secret'] ) ) {
		update_option( 'guru_instagram_client_secret', $_POST['guru_instagram_client_secret'] );
		$guru_instagram_client_secret = $_POST['guru_instagram_client_secret'];
	}
	if( isset( $_POST['guru_instagram_access_token'] ) ) {
		update_option( 'guru_instagram_access_token', $_POST['guru_instagram_access_token'] );
		$guru_instagram_access_token = $_POST['guru_instagram_access_token'];
	}
	if( isset( $_POST['guru_instagram_user_id'] ) ) {
		update_option( 'guru_instagram_user_id', $_POST['guru_instagram_user_id'] );
		$guru_instagram_user_id = $_POST['guru_instagram_user_id'];
	}
	if( isset( $_GET['code'] ) ) {
		$result = guru_instagram_get_access_token( $guru_instagram_client_id, $guru_instagram_client_secret, $redirect_url, $_GET['code'] );
		if( gettype($result) == 'object' ) {
			update_option( 'guru_instagram_access_token', $result->access_token );
			$guru_instagram_access_token = $result->access_token;
			update_option( 'guru_instagram_user_id', $result->user->id );
			$guru_instagram_user_id = $result->user->id;
		} else {
			echo 'Report error';
		}
	}


	?>
	
	<h1>GuRu Instagram</h1>

	<?php if( empty($guru_instagram_access_token) ) : ?>
	
	<a href="https://api.instagram.com/oauth/authorize/?client_id=<?php echo $guru_instagram_client_id; ?>&redirect_uri=<?php echo $redirect_url; ?>&response_type=code&scope=basic+public_content+follower_list+comments+relationships+likes">Authorize Website</a>
	<?php else : ?>

		<p>This website is authorized to make API requests to instagram.</p>

	<?php endif; ?>

	<form action="options-general.php?page=guru-instgram" method="post">
		<p>
			<label for="">Client ID</label>
			<input type="text" name="guru_instagram_client_id" value="<?php echo $guru_instagram_client_id; ?>">
		</p>
		<p>
			<label for="">Client Secret</label>
			<input type="text" name="guru_instagram_client_secret" value="<?php echo $guru_instagram_client_secret; ?>">
		</p>
		<p>
			<label for="">Access Token</label>
			<input type="text" name="guru_instagram_access_token" value="<?php echo $guru_instagram_access_token; ?>">
		</p>
		<p>
			<label for="">User ID</label>
			<input type="text" name="guru_instagram_user_id" value="<?php echo $guru_instagram_user_id; ?>">
		</p>
		<p>
			<input type="submit">
		</p>

	</form>


	<h2>Test Request</h2>
	<?php
		if( !empty($guru_instagram_access_token) ) {

			$url = 'https://api.instagram.com/v1/users/self/?access_token='.$guru_instagram_access_token;
		    $result = file_get_contents( $url );
		    $result = json_decode($result);
		    echo '<pre>'; print_r( $result ); echo '</pre>';

		} else {
			echo 'Once you have an access token the test will appear.';
		}

	echo '</div>';
}


function guru_instagram_get_access_token( $client_id, $client_secret, $registered_url, $code ) {

	$fields_string = '';
    $url = 'https://api.instagram.com/oauth/access_token';
    $fields = array(
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $registered_url,
        'code' => $code
    );
    
    //url-ify the data for the POST
    foreach( $fields as $key => $value ) { 
        $fields_string .= $key.'='.$value.'&'; 
    }
    rtrim($fields_string, '&');

    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_POST, count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

    //execute post
    $result = curl_exec($ch);

    //close connection
    curl_close($ch);

    return json_decode($result);
    // echo '<pre>'; print_r( json_decode($result) ); echo '</pre>';
}

function guru_instagram_show_feed( $instagram_hash = '' ) {

	$access_token = get_option('guru_instagram_access_token');
	
	if( empty($access_token) ) {
		echo 'Instagram not authorized.';
		return;
	}

	$images = array();

	if( !empty( $instagram_hash ) ) {
		
		$instagram_hash = trim($instagram_hash);

		$tags = explode(' ', $instagram_hash);
		
		foreach( $tags as $tag ) {
		    $url = 'https://api.instagram.com/v1/tags/'.$tag.'/media/recent?access_token='.$access_token;
		    $results = file_get_contents( $url );
		    $results = json_decode($results);
		    $results = $results->data;
		    foreach( $results as $result ) {
		    	$images[$result->link] = $result->images->thumbnail->url;
		    }
		}

	} else {

		$url = 'https://api.instagram.com/v1/users/self/media/recent?count=9&access_token='.$access_token;
	    $results = file_get_contents( $url );
	    $results = json_decode($results);
	    $results = $results->data;
	    foreach( $results as $result ) {
	    	$images[$result->link] = $result->images->thumbnail->url;
	    }
	}


	$images = array_unique($images);

	foreach( $images as $link => $src ) {
		echo '<a href="'.$link.'"><img src="'.$src.'" alt="" /></a>';
	}

}




?>
