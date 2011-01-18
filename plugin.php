<?php
/*
Plugin Name: Jump2.me para Wordpress
Plugin URI: http://jump2.me/blog/plugins/wordpress/
Description: Crie links curtos para os seus posts com o <a href="http://jump2.me" title="Jump2.me">jump2.me</a> e compartilhe-os através do Twitter.
Author: Luthiano Vasconcelos
Author URI: http://luthiano.com
Version: 1.0
*/

/* Release History :
 * 1.0:       Initial release
 */

global $wp_jump2me;
session_start();
require_once( dirname(__FILE__).'/inc/core.php' );


/******************** TAGS PARA USO NO TEMPLATE ********************/

// Use essa função para exibir o link curto para o post atual
function wp_jump2me_url() {
	global $id;
	$short = esc_url( apply_filters( 'jump2me_shorturl', wp_jump2me_geturl( $id ) ) );
	if ($short) {
		$rel    = esc_attr( apply_filters( 'jump2me_shorturl_rel', 'nofollow alternate shorturl shortlink' ) );
		$title  = esc_attr( apply_filters( 'jump2me_shorturl_title', 'Link curto' ) );
		$anchor = esc_html( apply_filters( 'jump2me_shorturl_anchor', $short ) );
		echo "<a href=\"$short\" rel=\"$rel\" title=\"$title\">$anchor</a>";
	}
}

// Recupera o link curto para um post.
function wp_jump2me_geturl( $id ) {
	do_action( 'jump2me_geturl' );
	
	$shorturl = get_post_meta( $id, 'jump2me_shorturl', true );
	$fetching = get_post_meta( $id, 'jump2me_fetching', true );
	
	// Se não houver um link curto, é necessário criá-lo
	if ( empty($shorturl) && !is_preview() && $fetching != 1 ) {
		$keyword = apply_filters( 'jump2me_custom_keyword', '', $id );
		$shorturl = wp_jump2me_get_new_short_url( get_permalink( $id ), $id, $keyword );
	}


	return $shorturl;
}

/************************ HOOKS ************************/

// Verifica a existência do PHP v5 no momento da ativação
register_activation_hook( __FILE__, 'wp_jump2me_activate_plugin' );
function wp_jump2me_activate_plugin() {
	if ( version_compare(PHP_VERSION, '5.0.0', '<') ) {
		deactivate_plugins( basename( __FILE__ ) );
		wp_die( 'Esse plugin precisa do PHP5. Foi mal!' );
	}
}

// Ações condicionais
if (is_admin()) {
	require_once( dirname(__FILE__).'/inc/options.php' );
	// Adiciona a menu de configurações e inicializa as opções e a interface de postagem
	add_action('admin_menu', 'wp_jump2me_add_page');
	add_action('admin_init', 'wp_jump2me_admin_init');
	//add_action('admin_init', 'wp_jump2me_addbox', 10);
	// Ícone personalizado e link de ativação do plugin
	add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'wp_jump2me_plugin_actions', -10);
	add_filter( 'adminmenu_icon_jump2me', 'wp_jump2me_customicon' );
} else {
	add_action('init', 'wp_jump2me_init', 1 );
}

// Trata as ações de publicação
add_action('new_to_publish', 'wp_jump2me_newpost', 10, 1);
add_action('draft_to_publish', 'wp_jump2me_newpost', 10, 1);
add_action('pending_to_publish', 'wp_jump2me_newpost', 10, 1);
add_action('future_to_publish', 'wp_jump2me_newpost', 10, 1);

// Substitui a função interna de criação de links curtos
add_filter( 'pre_get_shortlink', 'wp_jump2me_get_shortlink', 10, 3 );

