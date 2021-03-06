<?php
/*
Plugin Name: Jump2.me para Wordpress
Plugin URI: http://jump2.me/blog/plugins/wordpress/
Description: Crie links curtos para os seus posts com o <a href="http://jump2.me" title="Jump2.me">jump2.me</a> e compartilhe-os através do Twitter. Adicione o botão Tweet junto aos posts e estimule a divulgação do conteúdo do seu blog. Permita que seus usuários efetuem login através do Twitter para comentar seus posts.
Author: Luthiano Vasconcelos
Author URI: http://twitter.com/luthiano
Version: 1.6
*/

/* Release History :
 * 1.0: Versão inicial
 * 1.1: Evita o compartilhamento de posts antigos no Twitter.
 * 1.2: Metabox ativada. Bug na publicação de posts sem título.
 * 1.3: Botão tweet.
 * 1.4: Botão tweet com contador (vertical e horizontal). 
 * 1.5: Login de usuários através do Twitter.
 * 1.6: Acréscimo dos botões de login.
 */

global $wp_jump2me;
session_start();
require_once( dirname(__FILE__).'/inc/core.php' );


/******************** TAGS PARA USO NO TEMPLATE ********************/

// Use essa função para exibir o link curto para o post atual
function wp_jump2me_url() {
	$short = wp_jump2me_get_shorturl();
	if ($short) {
		$rel    = esc_attr( apply_filters( 'jump2me_shorturl_rel', 'nofollow alternate shorturl shortlink' ) );
		$title  = esc_attr( apply_filters( 'jump2me_shorturl_title', 'Link curto' ) );
		$anchor = esc_html( apply_filters( 'jump2me_shorturl_anchor', $short ) );
		return "<a href=\"$short\" rel=\"$rel\" title=\"$title\">$anchor</a>";
	} else {
		return "";
	}
}

function wp_jump2me_get_shorturl() {
	global $id;
	return esc_url( apply_filters( 'jump2me_shorturl', wp_jump2me_geturl( $id ) ) );
}

// Recupera o link curto para um post.
function wp_jump2me_geturl( $id ) {
	do_action( 'jump2me_geturl' );
	
	$shorturl = get_post_meta( $id, 'jump2me_shorturl', true );
	if ($shorturl == 'Por favor, informe o endereço') {
		$shorturl = '';
		update_post_meta( $post_id, 'jump2me_shorturl', '');
	}
	$fetching = get_post_meta( $id, 'jump2me_fetching', true );
	
	// Se não houver um link curto, é necessário criá-lo
	if ( empty($shorturl) && !is_preview() && $fetching != 1 ) {
		$keyword = apply_filters( 'jump2me_custom_keyword', '', $id );
		$shorturl = wp_jump2me_get_new_short_url( get_permalink( $id ), $id, $keyword, false );
		if ($shorturl == 'Por favor, informe o endereço') {
			$shorturl = '';
			update_post_meta( $post_id, 'jump2me_shorturl', '');
		}
	}

	// Se continua vazio, então retorna o permalink
	if (empty($shorturl)) {
		$shorturl = get_permalink($id);
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
	add_action('admin_init', 'wp_jump2me_addbox', 10);
	// Ícone personalizado e link de ativação do plugin
	add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'wp_jump2me_plugin_actions', -10);
	add_filter( 'adminmenu_icon_jump2me', 'wp_jump2me_customicon' );
} else {
	add_action('init', 'wp_jump2me_init', 1 );
}

// Trata as ações de publicação
$prioridade = 15;
//add_action('publish_page', 'wp_jump2me_newpost', $prioridade, 1);
//add_action('publish_phone', 'wp_jump2me_newpost', $prioridade, 1);
//add_action('xmlrpc_publish_post', 'wp_jump2me_newpost', $prioridade, 1);
add_action('publish_post', 'wp_jump2me_newpost', $prioridade, 1);
//add_action('publish_to_publish', 'wp_jump2me_newpost', $prioridade, 1);
add_action('new_to_publish', 'wp_jump2me_newpost', $prioridade, 1);
add_action('draft_to_publish', 'wp_jump2me_newpost', $prioridade, 1);
add_action('pending_to_publish', 'wp_jump2me_newpost', $prioridade, 1);
add_action('private_to_publish', 'wp_jump2me_newpost', $prioridade, 1);
add_action('future_to_publish', 'wp_jump2me_newpost', $prioridade, 1);

// Substitui a função interna de criação de links curtos
add_filter( 'pre_get_shortlink', 'wp_jump2me_get_shortlink', $prioridade, 3 );
// Adiciona o botão tweet no conteúdo do blog
add_filter('the_content', 'wp_jump2me_twitter_update', 9);

// Intercepta a função de recuperar o gravatar
add_filter('get_avatar', 'wp_jump2me_get_avatar', 10, 5);
add_filter("bp_core_fetch_avatar","wp_jump2me_get_avatar",10,5); //BuddyPress

// Enfilera o javascript - ok
//add_action('template_redirect', 'wp_jump2me_add_script');

add_action("wp_head", "wp_jump2me_head");
//add_action('wp_print_styles', 'wp_jump2me_stylesheet_add'); CSS adicionada no init
add_action('wp_admin_css','wp_jump2me_stylesheet_add');

// Formulário de login
add_action('login_form', 'wp_jump2me_login_form');
add_action('bp_after_sidebar_login_form', 'wp_jump2me_login_form'); //BuddyPress   


$btn_images = array();

$jump2me = get_option('jump2me'); 
if($jump2me['add_to_comment_page'] == 1)
{
    add_action('comment_form', 'wp_jump2me_comment_form');
}
if($jump2me['tweet_this'] == 1)
{
    add_action('comment_post', 'wp_jump2me_comment_post');
}


