<?php

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
	if (!empty($shorturl)) {
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
	
	update_post_meta( $post_id, 'jump2me_shorturl', $shorturl);
	update_post_meta( $post_id, 'jump2me_fetching', 0 );

	return $shorturl;
}

// Executa a API do Jump2.me. Returna um link curto.
function wp_jump2me_api_call($url, $post_id = 0, $keyword = '', $share = false) {
	global $wp_jump2me;
	
	$share_params = '';
	if ($share) {
		$share_params = '/share/1/tweet/'.urlencode(wp_jump2me_maketweet($post_id ));	
	}
	
	$keyword_params = '';
	if (!empty($keyword)) {
		$keyword_params = '/keyword/'.trim($keyword);
	}	
	
	$api_url = sprintf( 'http://jump2.me/api/shorten/api_key/%s/longlink/%s', $wp_jump2me['api_key'], urlencode($url)).
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
	$result = $request->request( $url , array( 'method'=>$method, 'body'=>$body, 'headers'=>$headers, 'user-agent'=>'Jump2.me para Wordpress http://jump2.me/blog/plugins/wordpress' ) );

	// Deu certo?
	if ( !is_wp_error($result) && isset($result['body']) ) {
		// Sim. Retorna o resultado. 
		return $result['body'];
	} else {
		// Não. E agora?
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

// Inicializa o plugin
function wp_jump2me_init() {
	global $wp_jump2me;
	$wp_jump2me = get_option('jump2me');	
}

// Inicializa o plugin (administração)
function wp_jump2me_admin_init() {
	global $wp_jump2me;
	$wp_jump2me = get_option('jump2me');

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

function wp_jump2me_twitter_generate_button()
{
	global $wp_jump2me;
	
	$button = '<div class="twitter_button" style="' . $wp_jump2me['twitter_style'] . '">';
    $button .= '<iframe src="http://jump2.me/services/button.php' . wp_jump2me_twitter_build_options() . '" ';

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

	$button .= 'height="' . $sizes[$wp_jump2me['twitter_lang']][$wp_jump2me['twitter_version']][0] . '" width="' . $sizes[$wp_jump2me['twitter_lang']][$wp_jump2me['twitter_version']][1] . '"';
	$button .= ' frameborder="0" scrolling="no" allowtransparency="true"></iframe></div>';
    return $button;
}

function wp_jump2me_twitter_update($content)
{
	global $post;
	global $wp_jump2me;

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
