<?php

// Retorna a versão do plugin
function wp_jump2me_get_version() {
	return '1.5';
}

// Retorna o servidor que deve ser utilizado:
function wp_jump2me_get_endpoint() {
	return 'jump2.me';
}
	
// Função executada quando um novo post é publicado.
function wp_jump2me_newpost( $post ) {
	global $wp_jump2me;
	
	do_action( 'jump2me_newpost' );

	$post_id = $post->ID;
	if (empty($post_id)) {
		$post_id = $post;
	}

	// Já possui um link curto?
	$shorturl = get_post_meta( $post_id, 'jump2me_shorturl', true );
	if (!empty($shorturl) && $shorturl != 'Por favor, informe o endereço') {
		return;
	}
	
	// Gera um link curto para esse tipo de objeto?
	$type = get_post_type($post_id);
	if ( !wp_jump2me_generate_on($type)) {
		return;
	}
	
	$url = get_permalink ($post_id);
	$url = apply_filters( 'jump2me_custom_url', $url, $post_id );
	
	// Verifica a existencia de uma keyword
	$keyword = get_post_meta( $post_id, 'jump2me_keyword', true );
	$keyword = apply_filters( 'jump2me_custom_keyword', $keyword, $post_id );
	
	// Verifica se deve compartilhar no Twitter
	$share = wp_jump2me_tweet_on($type);
	$shorturl = wp_jump2me_get_new_short_url($url, $post_id, $keyword, $share);

}

// Função de integração WP <-> JUMP2.ME. Retorna a URL curta de um post
function wp_jump2me_get_new_short_url( $url, $post_id = 0, $keyword = '', $share = false) {
	global $wp_jump2me;
	
	do_action( 'jump2me_get_new_short_url', $url, $post_id, $keyword, $share);
	
	// Marca que o flag 'jump2me_fetching' para inidicar que está gerando o link curto
	update_post_meta( $post_id, 'jump2me_fetching', 1 );
	
	// Faz a chamada a API do Jump2.me
	$shorturl = wp_jump2me_api_call( $url, $post_id, $keyword, $share);
	if ($shorturl != 'Por favor, informe o endereço') {
		update_post_meta( $post_id, 'jump2me_shorturl', $shorturl);
	} else {
		update_post_meta( $post_id, 'jump2me_shorturl', '');
	}
	update_post_meta( $post_id, 'jump2me_fetching', 0 );

	return $shorturl;
}

// Executa a API do Jump2.me. Returna um link curto.
function wp_jump2me_api_call($url, $post_id = 0, $keyword = '', $share = false) {
	global $wp_jump2me;
	
	// Verifica se já gerou um tweet para evitar repetições.
	$tweeted = get_post_meta( $post_id, 'jump2me_tweeted', true );
	if (empty($tweeted) || $tweeted != 1) {
		$share_params = '';
		if ($share) {
			$share_params = '/share/1/tweet/'.urlencode(wp_jump2me_maketweet($post_id ));	
		}
	}
	
	$keyword_params = '';
	if (!empty($keyword)) {
		$keyword_params = '/keyword/'.trim($keyword);
	}	
	
	$api_url = sprintf( 'http://%s/api/shorten/api_key/%s/longlink/%s', wp_jump2me_get_endpoint(), trim($wp_jump2me['api_key']), urlencode($url)).
				$keyword_params.$share_params;
	
	$shorturl = wp_jump2me_remote_simple( $api_url );
	
	if ($share == 1) {
		update_post_meta($post_id, 'jump2me_tweeted', 1);
	}

	return $shorturl;
}

// Executa uma chamada remota e retorna um simples string
function wp_jump2me_remote_simple( $url ) {
	return wp_jump2me_fetch_url( $url );
}

function wp_jump2me_fetch_url( $url, $method='GET', $body=array(), $headers=array() ) {
	if( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC. '/class-http.php' );
	$request = new WP_Http;
	$result = $request->request( $url , array( 'method'=>$method, 'body'=>$body, 'headers'=>$headers, 'user-agent'=>'Jump2.me para Wordpress '.wp_jump2me_get_version().'http://jump2.me/blog/plugins/wordpress' ) );
	// Deu certo?
	if ( !is_wp_error($result) && isset($result['body']) ) {
		// Sim. Retorna o resultado. 
		return $result['body'];
	} else {
		// Não. E agora?
		$error_string = $result->get_error_message();
		echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
		return false;
	}
}


// Cria a estrura básica do Tweet
function wp_jump2me_maketweet($id ) {
	global $wp_jump2me;
	
	$tweet = $wp_jump2me['twitter_message'];
	
	$tweet = apply_filters( 'pre_jump2me_tweet', $tweet, $id );
	
	// Substituição do $F{metadado} com o campo personalizado 'metadado'
	if( preg_match_all( '/$F\{([^\}]+)\}/', $tweet, $matches ) ) {
		foreach( $matches[1] as $match ) {
			$field = get_post_meta( $id, $match, true );
			$tweet = str_replace('$F{'.$match.'}', $field, $tweet);
		}
		unset( $matches );
	}
	
	// Obtem informação sobre o autor
	$post = get_post( $id );
	$author_id = $post->post_author;
	$author_info = get_userdata( $author_id );
	unset( $post );
	
	// Substituição do $A{metadado} com o metadado do autor
	if( preg_match_all( '/$A\{([^\}]+)\}/', $tweet, $matches ) ) {
		foreach( $matches[1] as $match ) {
			$tweet = str_replace('$A{'.$match.'}', $author_info->$match, $tweet);
		}
		unset( $matches );
	}
	
	// Substiuição do $A com o nome de exibição do autor
	$tweet = str_replace('$A', $author_info->display_name, $tweet);
	
	// Recupera tags (limitado a 3)
	$_tags = array_slice( (array)get_the_tags( $id ), 0, 3 );
	$tags = array();
	foreach( $_tags as $tag ) { $tags[] = strtolower( $tag->name ); }
	unset( $_tags );

	// Recupera categorias (limitado a 3)
	$_cats = array_slice( (array)get_the_category( $id ), 0, 3 );
	$cats = array();
	foreach( $_cats as $cat ) { $cats[] = strtolower( $cat->name ); }
	unset( $_cats );

	// Substituição do $L com tags em texto plano
	$tweet = str_replace('$L', join(' ', $tags), $tweet);
	
	// Substituição do $H com tags (como #hashtags)
	$tweet = str_replace('$H', '#'.join(' #', $tags), $tweet);
	
	// Substituição do $C com categorias
	$tweet = str_replace('$C', join(' ', $cats), $tweet);
	
	// Substituição do $D com categorias (como #hashtags) 
	$tweet = str_replace('$D', '#'.join(' #', $cats), $tweet);

	// Substituição do $T com o título do post
	$title = get_the_title($id);
	$tweet = str_replace('$T', $title, $tweet);

	$tweet = apply_filters( 'jump2me_tweet', $tweet, $url, $id );

	return $tweet;
}

//Valida a chave de sessão e inicializa login no Wordpress
function wp_jump2me_validate_session($session_key, $user_id)
{
    $url = 'http://'.wp_jump2me_get_endpoint().'/jump2/validate_session/'.urlencode($session_key).'/'.$user_id;
	$response = wp_jump2me_remote_simple($url);
	if ($response) {
	    $results = json_decode($response);
		if ($results->status == 'success') {
			return wp_jump2me_login($results->user);  
		} else {
			return false;
		}
	} else {
		// Ocorreu alguma coisa errada!
		echo "Boró";
	}
	
}

function wp_jump2me_login($jump2me_user) {
  	global $wpdb, $wp_jump2me;

  	$user_login_n_suffix = $jump2me_user->username.$wp_jump2me['user_login_suffix'];
	
	if ($wp_jump2me['use_twitter_profile'] == 1) {
		$user_url = 'http://twitter.com/'.$jump2me_user->username;
	} else {
		$user_url = $jump2me_user->url;
	}
  
  	$email_default = str_replace('%%username%%', $jump2me_user->username, $wp_jump2me['email_default']);

  	$userdata = array(
    'user_pass' => wp_generate_password(),
    'user_login' => $user_login_n_suffix,
    'display_name' => $jump2me_user->fullname,
    'user_url' => $user_url,
    'user_email' => $email_default
  );

  if(!function_exists('wp_insert_user'))
  {
      include_once( ABSPATH . WPINC . '/registration.php' );
  } 
  
  $wpuid = wp_jump2me_twitteruser_to_wpuser($jump2me_user->id);
  
  if(!$wpuid)
  {
      if (!username_exists($user_login_n_suffix))
      {
	 	if (!email_exists($email_default)) {
			$wpuid = wp_insert_user($userdata);
		} else {
			$wpid = wp_jump2me_get_userdata_by_email($email_default);
		}
        if($wpuid)
        {
            update_user_meta($wpuid, 'jump2me_id', $jump2me_user->id);
        }
      }
      else
      {
		// TODO: Vincular o WPID com Jump2.me ID
        wp_die('Usuário '.$user_login_n_suffix.' já estava registrado.');
      }
  }
  else
  {
    $user_obj = get_userdata($wpuid);
    
    if($user_obj->display_name != $jump2me_user->fullname || $user_obj->user_url != $user_url)
    {
        $userdata = array(
        'ID' => $wpuid,
        'display_name' => $jump2me_user->fullname,
        'user_url' => $user_url,
        );
        wp_update_user( $userdata );
    }
    if($user_obj->user_login != $user_login_n_suffix)
    {
        if (!username_exists($user_login_n_suffix))
        {
            $q = sprintf( "UPDATE %s SET user_login='%s' WHERE ID=%d", 
                $wpdb->users, $user_login_n_suffix, (int) $wpuid );
		    if (false !== $wpdb->query($q)){
		        update_user_meta( $wpuid, 'nickname', $user_login_n_suffix );
		    }
		}
        else
        {
          wp_die('Usuário '.$user_login_n_suffix.' já estava registrado.');
        }
    }
  }
  
  if($wpuid) {
        wp_set_auth_cookie($wpuid, true, false);
        wp_set_current_user($wpuid);
  }
}

// Inicializa o plugin
function wp_jump2me_init() {
	global $wp_jump2me;
	$wp_jump2me = get_option('jump2me');
	
	if(!is_user_logged_in())
    {
		if(isset($_GET['jump2me_session_key']) && isset($_GET['jump2me_user_id']))
	    {
	        wp_jump2me_validate_session($_GET['jump2me_session_key'], $_GET['jump2me_user_id']);
	    }
	}
}

// Inicializa o plugin (administração)
function wp_jump2me_admin_init() {
	
	wp_jump2me_init();

	register_setting( 'wp_jump2me_options', 'jump2me', 'wp_jump2me_sanitize' );

	if ( !wp_jump2me_settings_are_ok() ) {
		add_action( 'admin_notices', 'wp_jump2me_admin_notice' );
	}
}

// Verifica se é necessário gerar um link curto de acordo com o tipo do objeto
function wp_jump2me_generate_on( $type ) {
	global $wp_jump2me;
	return ( isset( $wp_jump2me['generate_on_'.$type] ) && $wp_jump2me['generate_on_'.$type] == 1 );
}

// Verifica se é necessário enviar um tweet de acordo com o tipo do objeto
function wp_jump2me_tweet_on( $type ) {
	global $wp_jump2me;
	return ( isset( $wp_jump2me['tweet_on_'.$type] ) && $wp_jump2me['tweet_on_'.$type] == 1 );
}


// Gera um ícone personalizado para o plugin
function wp_jump2me_customicon( $in ) {
	return wp_jump2me_pluginurl().'res/icon.png';
}

// Adiciona o link 'Configurações' na página do plugin
function wp_jump2me_plugin_actions($links) {
	$links[] = "<a href='options-general.php?page=jump2me'><b>Configurações</b></a>";
	return $links;
}


// Substitui a função do Wordpress wp_get_shortlink.
function wp_jump2me_get_shortlink( $false, $id, $context = '' ) {
	
	global $wp_query;
	$post_id = 0;
	if ( 'query' == $context && is_single() ) {
		$post_id = $wp_query->get_queried_object_id();
	} elseif ( 'post' == $context ) {
		$post = get_post($id);
		$post_id = $post->ID;
	}
	
	if( !$post_id && $context == 'post' )
		return null;
	
	if( !$post_id && $context == 'query' ) 
		return null;
	
	$type = get_post_type( $post_id );
	if( !wp_jump2me_generate_on( $type ) )
		return null;
		
	// Verifica se o post está publicado.
	if( 'publish' != get_post_status( $post_id ) )
		return null;

	// Gera um link curto
	return wp_jump2me_geturl( $post_id );
}

// Retorna a URL do plugin
function wp_jump2me_pluginurl() {
	return plugin_dir_url( dirname(__FILE__) );
}


// *************** Botão Tweet *********************/

function wp_jump2me_twitter_build_options()
{
	global $post;
	global $wp_jump2me;
	
	if (get_post_status($post->ID) == 'publish') {
        //$url = get_permalink($post->ID);
		$url = wp_jump2me_geturl($post->ID);
    }
    $button = '?url=' . urlencode($url);

    // tipo do botão
    if ($wp_jump2me['twitter_version']) {
        $button .= '&count=' . urlencode($wp_jump2me['twitter_version']);
    }

	if ($wp_jump2me['twitter_via']) {
		$button .= '&via=' . urlencode($wp_jump2me['twitter_via']);
	}

	if ($wp_jump2me['twitter_lang']) {
		$button .= '&lang=' . urlencode($wp_jump2me['twitter_lang']);
	}

	// O post tem um texto padrão configurado?
	if (($text = get_post_meta($post->ID, 'twitter_text', true)) != false) {
		$button .= '&text=' . urlencode($text);
	} else {
		// Senão usa o título do post
		$button .= '&text=' . urlencode(get_the_title($post->ID));
	}

    return $button;
}

function wp_jump2me_twitter_comments_build_options()
{
	global $wp_jump2me;
	
	$ct_comment_id = get_comment_ID();
	$url = get_comment_link();
	$tweet = get_comment_text(stripslashes(trim($ct_comment_id)));
	
    if (strlen($tweet) > 125) {
		$tweet = substr($tweet, 0, 120) . '...';
	}
	
	$button = '?url=' . urlencode($url);

    // tipo do botão = simples
	$button .= '&count=none';

	if ($wp_jump2me['twitter_lang']) {
		$button .= '&lang=' . urlencode($wp_jump2me['twitter_lang']);
	}

	$button .= '&text=' . urlencode($tweet);

    return $button;
}

function wp_jump2me_twitter_generate_button($twitter_version = '', $twitter_lang = '', $twitter_style = '', $button_in_comments = false)
{
	global $wp_jump2me;
	
	if (empty($twitter_version)) {
		$twitter_version = $wp_jump2me['twitter_version'];
	}
	if (empty($twitter_lang)) {
		$twitter_lang = $wp_jump2me['twitter_lang'];
	}
	if (empty($twitter_style)) {
		$twitter_style = $wp_jump2me['twitter_style'];
	}
	
	$button = '<div class="twitter_button" style="' . $twitter_style . '">';
	if ($button_in_comments) {
		$button .= '<iframe src="http://'.wp_jump2me_get_endpoint().'/services/button.php' . wp_jump2me_twitter_comments_build_options() . '" ';
	} else {
    	$button .= '<iframe src="http://'.wp_jump2me_get_endpoint().'/services/button.php' . wp_jump2me_twitter_build_options() . '" ';
	}

	$sizes = array(
		'pt_BR' => array(
			'vertical' => array(62, 55),
			'horizontal' => array(20, 110),
			'none' => array(20, 55)
		),
		'en' => array(
			'vertical' => array(62, 55),
			'horizontal' => array(20, 110),
			'none' => array(20, 55)
		),
		'fr' => array(
			'vertical' => array(62, 65),
			'horizontal' => array(20, 117),
			'none' => array(20, 65)
		),
		'de' => array(
			'vertical' => array(62, 67),
			'horizontal' => array(20, 119),
			'none' => array(20, 67)
		),
		'es' => array(
			'vertical' => array(62, 64),
			'horizontal' => array(20, 116),
			'none' => array(20, 64)
		),
		'ja' => array(
			'vertical' => array(62, 80),
			'horizontal' => array(20, 130),
			'none' => array(20, 80)
		)
	);

	$button .= 'height="' . $sizes[$twitter_lang][$twitter_version][0] . '" width="' . $sizes[$twitter_lang][$twitter_version][1] . '"';
	$button .= ' frameborder="0" scrolling="no" allowtransparency="true"></iframe></div>';
    return $button;
}

function wp_jump2me_twitter_update($content)
{
	global $post;
	global $wp_jump2me;
	
	/* 
		TODO: Incoporar suporte ao Blackbird Pie

	   $tweet_pattern = '/(\[tweet\](.*?)\[\/tweet\])/is';

	    # Check for in-post [tweet] [/tweet]
	    if (preg_match_all ($tweet_pattern, $text, $matches)) {
	        for ($m=0; $m<count($matches[0]); $m++) {
	            $tweet = TweetQuote($matches[2][$m]);
	            $text = str_replace($matches[0][$m],$tweet,$text);
	        } 
	    }
	
	*/
	

	if ($wp_jump2me['twitter_enable'] == 'yes') {
		$button = wp_jump2me_twitter_generate_button();

	    // Se inclusão manual
	    if ($wp_jump2me['twitter_where'] == 'manual') {
	        return $content;
		}
	    if (!array_key_exists('twitter_display_page', $wp_jump2me) && is_page()) {
	        return $content;
	    }
		if (!array_key_exists('twitter_display_front', $wp_jump2me) && is_home()) {
	        return $content;
	    }
		if (is_feed()) {
			return $content;
		}

		// Shortcode [twitter]
		if ($wp_jump2me['twitter_where'] == 'shortcode') {
			return str_replace('[twitter]', $button, $content);
		} else {
			if (get_post_meta($post->ID, 'twitter') == null) {
				if ($wp_jump2me['twitter_where'] == 'beforeandafter') {
					return $button . $content . $button;
				} else if ($wp_jump2me['twitter_where'] == 'before') {
					return $button . $content;
				} else {
					return $content . $button;
				}
			} else {
				return $content;
			}
		}
	} else {
		return $content;
	}
}

function wp_jump2me_comment_button($content) {
	$twitter_version = 'none';
	$twitter_lang = '';
	$twitter_style = '';
	$button_in_comments = true;
	$content = $content . wp_jump2me_twitter_generate_button();
	return $content;
}

/**
 * Altera o avatar
 *
 * @param <type> $avatar
 * @param <type> $id_or_email
 * @param <type> $size
 * @param <type> $default
 * @param <type> $alt
 */
function wp_jump2me_get_avatar($avatar, $id_or_email, $size = '32', $default, $alt) {
	global $comment, $wp_jump2me;
	
	 if(is_object($comment))
	 {
	     $id_or_email = $comment->user_id;
	 }

	 if (is_object($id_or_email)) {
	    $id_or_email = $id_or_email->user_id;
	 }

	 if (get_usermeta($id_or_email, 'jump2me_id')) {
		$profile_images = wp_jump2me_profile_images_get();		
		$user_info = get_userdata($id_or_email);
		$username = str_replace("@jump2.me","",$user_info->user_login);
		$display_name = $user_info->display_name;
		$out = str_replace('%%username%%',urlencode($username),$profile_images);
	 } else {
		$out = "http://www.gravatar.com/avatar.php?gravatar_id=".md5($comment->comment_author_email);
		$display_name = '';
	}
	$avatar = "<img alt='{$display_name}' src='{$out}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
	return $avatar;
}


function wp_jump2me_connect()
{
    global $loaded;
    wp_jump2me_show_login_button();
    $loaded = true;
}

function wp_jump2me_login_form()
{	
	global $wp_jump2me;
	
    if($wp_jump2me['add_to_login_page'] == 1)
    {
        wp_jump2me_show_login_button(0,'login');
    }
}

function wp_jump2me_comment_form() {  
    wp_jump2me_show_login_button();
}

function wp_jump2me_head()
{
    global $user_email;
	global $wp_jump2me;
    
    if(is_user_logged_in())
    {
        $url = '';
       
 		/*
  	    if($wp_jump2me['comment_redirect'] == 1)
	    {
            if(strpos($user_email, '.temp') !== false)
	        {
	            $url = 'wp-admin/profile.php';
	        }
	    }
	 */
	    
        echo '<script type="text/javascript">'."\r\n";
        echo '<!--'."\r\n";
        echo 'if(window.opener){if(window.opener.document.getElementById("jump2me_connect") || window.opener.getElementsByClass("jump2me_connect")){window.opener.jump2me_bookmark("'.$url.'");window.close();}}'."\r\n";
        echo '//-->'."\r\n";
        echo '</script>';
    }
}

function wp_jump2me_show_login_button($id='0',$type='comment')
{
    global $user_ID, $user_email, $tweet_this, $loaded, $url, $page, $a;
	global $wp_jump2me;
	
	echo '<link rel="stylesheet" href="'.wp_jump2me_pluginurl(). 'res/jump2me.css" type="text/css" media="all" />';    
   
    if(is_user_logged_in())
    {
        if($type == 'login')
        {
            echo '<script type="text/javascript">'."\r\n";
            echo '<!--'."\r\n";
            echo 'if(window.opener){if(window.opener.document.getElementById("#jump2me_connect") || window.opener.getElementsByClass("jump2me_connect")){window.opener.jump2me_bookmark("");window.close();}}'."\r\n";
            echo '//-->'."\r\n";
            echo '</script>';
        }
        else
        {
			if (!empty($wp_jump2me['tweet_this'])) {
	            if($wp_jump2me['tweet_this'] == '1' && get_usermeta($user_ID, 'jump2me_id'))
	            {
	                echo '<p class="jump2me-tweet-this"><input type="checkbox" id="tweet_this" name="tweet_this" style="width:auto" /> Publicar no Twitter [<a href="javascript:none" title="Publicar esse comentário no Twitter">?</a>]</p>';
	            }
	            echo '<p>Atualize seu endereço de e-mail: <a href="./wp-admin/profile.php" name="jump2me-button">'.$user_email.'</a></p>';
	            echo '<script type="text/javascript">'."\r\n";
	            echo '<!--'."\r\n";
	            echo 'window.onload=function(){if(!window.opener && document.getElementById("comment")){'."\r\n";
				echo 'if(window.opener){if(window.opener.document.getElementById("#jump2me_connect") || window.opener.getElementsByClass("jump2me_connect")){window.opener.jump2me_bookmark("");window.close();}}'."\r\n";
	            echo '    if(document.getElementById("comment").value.length == 0)'."\r\n";
	            echo '    {'."\r\n";
	            echo '        jump2me_updateComment(jump2me_readCookie("jump2me_comment"));'."\r\n";
	            echo '    }'."\r\n";
	            echo '}};'."\r\n";
	            echo '//-->'."\r\n";
	            echo '</script>'."\r\n";
			}            
        }
    }
    
     echo '<script type="text/javascript">
    <!--
    //No jQuery
    if(typeof jQuery == "undefined")
    {
        window.onload = function(){
            if(document.getElementById("jump2me_connect"))
            {
                if(!document.getElementById("jump2me_connect").getAttribute("loaded"))
                {
                    jump2me_createButton(document.getElementById("jump2me_connect"));
                }
            }
            var elems = getElementsByClass("jump2me_connect");
            for(var ndx=0;ndx<elems.length;ndx++)
            {
                if(!elems[ndx].getAttribute("loaded"))
                {
                    jump2me_createButton(elems[ndx]);
                }
            }
        };
    }
    else
    {
        jQuery(document).ready(function(){
            jQuery("#jump2me_connect").each(function(i){
                if(!jQuery(this).attr("loaded"))
                {
                    jump2me_createButton(jQuery(this));
                }
            });
            jQuery(".jump2me_connect").each(function(i){
                if(!jQuery(this).attr("loaded"))
                {
                    jump2me_createButton(jQuery(this));
                }
            });
        });
    }
    //-->
    </script>';
    
    if(!$loaded)
    {
        //************************************************************************************
        //* Cookie Javascript
        //************************************************************************************
        echo '<script type="text/javascript">
        <!--
            function jump2me_createCookie(name,value,days) {
	            if (days) {
		            var date = new Date();
		            date.setTime(date.getTime()+(days*24*60*60*1000));
		            var expires = "; expires="+date.toGMTString();
	            }
	            else var expires = "";
	            document.cookie = name+"="+value+expires+"; path=/";
            }
            function jump2me_readCookie(name) {
	            var nameEQ = name + "=";
	            var ca = document.cookie.split(\';\');
	            for(var i=0;i < ca.length;i++) {
		            var c = ca[i];
		            while (c.charAt(0)==\' \') c = c.substring(1,c.length);
		            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	            }
	            return null;
            }
            function jump2me_eraseCookie(name) {
	            jump2me_createCookie(name,"",-1);
            }
            function jump2me_updateComment(comment) { 
                if(comment){
                    document.getElementById("comment").value = comment.replace(/<br\/>/g,"\n");
                    jump2me_eraseCookie("comment");
                    
                }
            }
            function getElementsByClass( searchClass, domNode, tagName) { 
	            if (domNode == null) domNode = document;
	            if (tagName == null) tagName = "*";
	            var el = new Array();
	            var tags = domNode.getElementsByTagName(tagName);
	            var tcl = " "+searchClass+" ";
	            for(i=0,j=0; i<tags.length; i++) { 
		            var test = " " + tags[i].className + " ";
		            if (test.indexOf(tcl) != -1) 
			            el[j++] = tags[i];
	            } 
	            return el;
            }         
            //-->
            </script>';               
        //************************************************************************************
        //* End Cookie Javascript
        //************************************************************************************
    }

    if(is_user_logged_in())
    {
        return;
    }
    
    $before = '';
    if($type == 'login')
    {
        $login_text = htmlspecialchars_decode($wp_jump2me['login_text']);
        echo '<br/>'.$login_text;
        $before = $wp_jump2me['before_login'];
    }
    else
    {
    	$template = htmlspecialchars_decode($wp_jump2me['template']);
        echo $template;
        $before = $wp_jump2me['before_comment'];
    }
    
    $redirect = $wp_jump2me['redirect'];  
    $btn_image = $wp_jump2me['btn_choice'];
    
    if(strpos($btn_image, 'http') === false )
    {
       $btn_images = jump2me_get_buttons(); 
       $wp_jump2me['btn_choice'] = end($btn_images);
       $btn_image = end($btn_images);
    }
    
    if($loaded)
    {
        return;
    }
   
    //************************************************************************************
    //* Button Javascript???
    //************************************************************************************
    echo '<script type="text/javascript">
    <!--
function urlencode(str) {
	return encodeURIComponent(str).replace(/\+/g,"%2B")
}

function urldecode(str) {
str = str.replace("+", " ");
str = unescape(str);
return str;
}

    function jump2me_bookmark(url){
       var reload = false;
       if(url == location.href)       
       {
            reload = true;
       }
       if(url.length == 0)
       {
            url=location.href;
            reload = true;
       }
       if(url.indexOf("wp-login.php") > 0)
       {
           url = "'.$redirect.'";
           location.href = url;
       }
       else
       {
           var temp = url.split("#");
           url = temp[0];
           url += "#jump2me_button";
           location.href = url;
           if(reload)
           {
              location.reload();
           }
       }
    }
    
    function jump2me_createButton(obj)
    {
        var url = location.href;
        var button = document.createElement("button");
        button.setAttribute("class","btn");
        button.setAttribute("type","button");
        button.setAttribute("tabindex","999");
        button.onclick = function(){
            if(document.getElementById("comment"))
            {
                if(document.getElementById("comment").value.length > 0)
                {
                    var comment = document.getElementById("comment").value;
                    comment = comment.replace(/\r\n/g,"\n").replace(/\n/g,"<br/>");
                    jump2me_createCookie("comment",comment,1);
                    var cookie = jump2me_readCookie("comment");
                    if(cookie != comment)
                    {
                        jump2me_eraseCookie("jump2me_comment");
                        return false;
                    }
                }
            }
            window.open("http://'.wp_jump2me_get_endpoint().'/jump2/login/"+urlencode(url), "jump2meWindow","width=800,height=400,left=150,top=100,scrollbar=no,resize=no");
            return false;
        };
        button.innerHTML = "<img src=\''.$btn_image.'\' alt=\'Login\' style=\'margin:0;\' />";
        if(typeof jQuery == "undefined")
        {
            obj.appendChild(button);
            obj.setAttribute("loaded","true");
        }
        else
        {
	        obj.append(button);
	        obj.attr("loaded","true");
	    }
	    ';
        /* PHP */
        if(strlen($before) > 0)
        {
            echo 'if(document.getElementById("'.$before.'"))
                {
                    var before = document.getElementById("'.$before.'");
                    before.parentNode.insertBefore(document.getElementById("jump2me_connect"),before);
                }
                ';
        }
        /* END PHP */        
        echo '}
        //-->
        </script>';
    //************************************************************************************
    //* End - Button Javascript
    //************************************************************************************

    $loaded = true;

}

function wp_jump2me_stylesheet_add() {
    $src = wp_jump2me_pluginurl() . 'res/jump2me.css';
    wp_enqueue_style('jump2me_style', $src);
} 


function wp_jump2me_profile_images_get()
{
	global $wp_jump2me;
	
	$profile_images = $wp_jump2me['profile_images'];

    if(empty($profile_images))
    {
        $profile_images = 'http://api.twitter.com/1/users/profile_image/%%username%%'; 
    }
    
    return $profile_images;
}


function wp_jump2me_comment_post($comment_ID)
{
    global $wp_jump2me;
    
    if(!isset($_REQUEST["tweet_this"]))
    {
        return;
    }

    $comment = get_comment($comment_ID); 
    $post_title = strip_tags(get_the_title( $comment->comment_post_ID ));
    $blog_title = get_bloginfo('name');
    
    $permalink = '';
    if($comment->comment_approved == 1)
    {
        $permalink = get_comment_link($comment);
    }
    else
    {
        $permalink = get_permalink($comment->comment_post_ID);
    }   

    $tweet = $wp_jump2me["tweet_this_text"];
    
    //TODO: implementar Título do Blog
    $temp_tweet = $tweet;
    $temp_tweet = str_replace('$T', '', $temp_tweet);
    //$temp_tweet = str_replace('%%blog_title%%', '', $temp_tweet);
    //$temp_tweet = str_replace('$U', '', $temp_tweet);

    $tweet_len = strlen($temp_tweet);
	$reserved = 15;
    if(strlen($post_title) + strlen($blog_title) + $reserved + $tweet_len > 140)
    {
        $ctr = strlen($blog_title) - 1;
        $shorter = false;
        while(strlen($blog_title) > 10 && 140 < strlen($post_title) + strlen($blog_title) + 3 + $reserved + $tweet_len)
        {
            $blog_title = substr($blog_title,0,$ctr--);  
            $shorter = true;
        }
        if($shorter)
        {
            $blog_title.='...';
        }
        $ctr = strlen($post_title) - 1;
        $shorter = false;
        while(strlen($post_title) > 10 && 140 < strlen($post_title) + 3 + strlen($blog_title) + $reserved + $tweet_len)
        {
            $post_title = substr($post_title,0,$ctr--);  
            $shorter = true;
        }
        if($shorter)
        {
            $post_title.='...';
        }
    } 
    $temp_tweet = $tweet;
    $temp_tweet = str_replace('$T',$post_title, $temp_tweet);
    //$temp_tweet = str_replace('%%blog_title%%',$blog_title, $temp_tweet);
    //$temp_tweet = str_replace('$U',$shortlink, $temp_tweet);
    
    $tweet = $temp_tweet;
    if(strlen($tweet) <= 140)
    {
        // TODO: Como fazer para usar a API do visitante?
		$share_params = '/share/1/tweet/'.urlencode($tweet);	
		
		$api_url = sprintf( 'http://%s/api/shorten/api_key/%s/longlink/%s', wp_jump2me_get_endpoint(), trim($wp_jump2me['api_key']), urlencode($permalink)).$share_params;
		$shorturl = wp_jump2me_remote_simple( $api_url );
		
    }    
    
}

function wp_jump2me_get_userdata_by_email($email) {
	global $wpdb;
	$sql = "SELECT ID FROM $wpdb->users WHERE user_email = '%s'";
	return $wpdb->get_var($wpdb->prepare($sql, $email));
}

function wp_jump2me_get_user_by_meta($meta_key, $meta_value) {
  global $wpdb;
  $sql = "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '%s' AND meta_value = '%s'";
  return $wpdb->get_var($wpdb->prepare($sql, $meta_key, $meta_value));
}

function wp_jump2me_twitteruser_to_wpuser($jump2me_id) {
  return wp_jump2me_get_user_by_meta('jump2me_id', $jump2me_id);
}

function wp_jump2me_get_domain()
{
    // get host name from URL
    $siteurl = str_replace('https://','http://',get_option("home"));
    preg_match('@^(?:http://)?([^/]+)@i', $siteurl, $matches);
    $host = $matches[1];

    // get last two segments of host name
    preg_match('/[^.]+\.[^.]+$/', $host, $matches);
    return $matches[0];
}

function jump2me_get_buttons()
{
    global $btn_images;
    
    $path = dirname(dirname(__FILE__)).'/res/imgs_login/';
	$handle=opendir($path);
    $uri = wp_jump2me_pluginurl().'res/imgs_login/';
    

    while (($file = readdir($handle))!==false) {
        $ext = end(explode(".", $file));
        if($ext == 'png' || $ext == 'gif' || $ext == 'jpg'){
            array_push($btn_images, $uri.$file);
        }
    }
    closedir($handle);
    
    return $btn_images;
}

/*
function TweetQuote($text)
{
	$tweet_quote_template = get_option('tweet_quote_template');	
    $start = 0;
    $end = 1;
    $screen_name = "";
    $tweet = "";
    $new_text = "";
    $tweets = explode('·', $text);
    foreach($tweets as $tweet)
    {
        $new_tweet = "";    
        $end = strpos($tweet, ':');
        $start = $end;
        while($start > 0 && ($end - $start) <= 16   )
        {
            $start--;
            if(substr($tweet, $start ,1) == ' ' || substr($tweet, $start ,1) == ']' || substr($tweet, $start ,1) == '>')
            {
                $start++;
                break;
            }
            
        }
        if($start >= 0 && ($end - $start) <= 16)
        {
            $tweet = strip_tags(substr($tweet, $start));
            $start = 0;
            $end = strpos($tweet, ":");
            $screen_name = substr($tweet, $start, $end - $start);
            $tweet = str_replace($screen_name.":","", $tweet);
            $tweet = str_replace('(expand)', '', $tweet);
            $tweet = wp_jump2me_linkify_twitter_status($tweet);
            $tweet = str_replace("\n",'<span class="timestamp">', $tweet);
            $tweet = $tweet.'</span>';
            $new_tweet = $tweet_quote_template;
            $new_tweet = str_replace('%%username%%', $screen_name, $new_tweet);
            $new_tweet = str_replace('%%tweet%%', $tweet, $new_tweet);
        }
        if(strlen($screen_name) > 0)
        {
            $new_text .= $new_tweet;
        }
    }
    return $new_text;
}

function wp_jump2me_linkify_twitter_status($status_text)
{
  // linkify URLs
  $status_text = preg_replace(
    '/(https?:\/\/\S+)/',
    '<a href="\1">\1</a>',
    $status_text
  );

  // linkify twitter users
  $status_text = preg_replace(
    '/(^|\s)@(\w+)/',
    '\1@<a href="http://twitter.com/\2">\2</a>',
    $status_text
  );

  // linkify tags
  $status_text = preg_replace(
    '/(^|\s)#(\w+)/',
    '\1#<a href="http://search.twitter.com/search?q=%23\2">\2</a>',
    $status_text
  );

  return $status_text;
}

*/

