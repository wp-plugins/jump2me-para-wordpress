<?php

// Exibe aviso para definir configurações 
function wp_jump2me_admin_notice() {
	global $plugin_page;
	if( $plugin_page == 'jump2me' ) {
		$message = '<strong>Jump2.me para Wordpress</strong> não está completamente configurado';
	} else {
		$url = menu_page_url( 'jump2me', false );
		$message = 'Por favor, configure <a href="'.$url.'">aqui</a> o <strong>Jump2.me para Wordpress</strong>';
	}
	$notice = <<<NOTICE
	<div class="error"><p>$message</p></div>
NOTICE;
	echo apply_filters( 'jump2me_notice', $notice );
}

// Adiciona página ao menu
function wp_jump2me_add_page() {
	$page = add_options_page('Jump2.me para Wordpress', 'jump2.me', 'manage_options', 'jump2me', 'wp_jump2me_do_page');
	add_action("load-$page", 'wp_jump2me_add_css_js_plugin');
	add_action("load-$page", 'wp_jump2me_handle_action_links');
	add_action('load-post.php', 'wp_jump2me_add_css_js_post');
	add_action('load-post-new.php', 'wp_jump2me_add_css_js_post');
	add_action('load-page.php', 'wp_jump2me_add_css_js_post');
	add_action('load-page-new.php', 'wp_jump2me_add_css_js_post');
}

// Adiciona CSS e JS na página do plugin
function wp_jump2me_add_css_js_plugin() {
	add_thickbox();
	$plugin_url = wp_jump2me_pluginurl();
	wp_enqueue_script('jump2me_js', $plugin_url.'res/jump2me.js');
	wp_enqueue_script('wp-ajax-response');
	wp_enqueue_style('jump2me_css', $plugin_url.'res/jump2me.css');
}

// Adiciona CSS e JS na página de edição
function wp_jump2me_add_css_js_post() {
	global $pagenow;
	$current = str_replace( array('-new.php', '.php'), '', $pagenow);
	if ( wp_jump2me_generate_on($current) ) {
		$plugin_url = wp_jump2me_pluginurl();
		wp_enqueue_script('jump2me_js', $plugin_url.'res/post.js');
		wp_enqueue_style('jump2me_css', $plugin_url.'res/post.css');
	}
}

// Valida opçes de configuração
function wp_jump2me_sanitize( $in ) {
	global $wp_jump2me;
	
	$in = array_map( 'esc_attr', $in);
	
	foreach( $in as $key=>$value ) {
		if( preg_match( '/^(generate|tweet)_on_/', $key ) ) {
			$in[$key] = ( $value == 1 ? 1 : 0 );
		}
	}
	
	return $in;
}

// Verifica se o plugin está configurado
function wp_jump2me_settings_are_ok( $check = 'overall' ) {
	global $wp_jump2me;

	$check_jump2me   = ( isset( $wp_jump2me['api_key'] ) && !empty( $wp_jump2me['api_key'] ) ? true : false );
	$check_wordpress = ( isset( $wp_jump2me['twitter_message'] ) && !empty( $wp_jump2me['twitter_message'] ) ? true : false );
	
	if( $check == 'overall' ) {
		$overall = $check_jump2me && $check_wordpress ;
		return $overall;
	} else {
		return array( 'check_jump2me' => $check_jump2me, 'check_wordpress' => $check_wordpress );
	}
}

// Trata links de ação (reset)
function wp_jump2me_handle_action_links() {
	$actions = array( 'reset', 'unlink' );
	if( !isset( $_GET['action'] ) or !in_array( $_GET['action'], $actions ) )
		return;

	$action = $_GET['action'];
	$nonce  = $_GET['_wpnonce'];
	
	if ( !wp_verify_nonce( $nonce, $action.'-jump2me') )
		wp_die( "Invalid link" );
	
	global $wp_jump2me;
		
	switch( $action ) {
	
		case 'unlink':
			wp_jump2me_session_destroy();
			update_option( 'jump2me', $wp_jump2me );
			break;

		case 'reset':
			wp_jump2me_session_destroy();
			$wp_jump2me = array();
			delete_option( 'jump2me' );
			break;

	}
	
	wp_redirect( menu_page_url( 'jump2me', false ) );
}

// Encerra a sessão
function wp_jump2me_session_destroy() {
	$_SESSION = array();
	if ( isset( $_COOKIE[session_name()] ) ) {
	   setcookie( session_name(), '', time()-42000, '/' );
	}
	session_destroy();
}

// Monta a página de configurações
function wp_jump2me_do_page() {
	$plugin_url = wp_jump2me_pluginurl();
	
	$jump2me = get_option('jump2me'); 
	
	extract( wp_jump2me_settings_are_ok( 'all' ) ); // $check_jump2me, $check_wordpress
	
	// If only one of the 3 $check_ is false, expand that section, otherwise expand first
	switch( intval( $check_jump2me ) + intval( $check_wordpress ) ) {
		case 0:
			break;
		case 1:
			if( !$check_jump2me ) {
				$script_expand = "jQuery('#h3_jump2me').click();";
			} else {
				$script_expand = "jQuery('#h3_wordpress').click();";
			}
			break;
		case 2:
			$script_expand = "jQuery('#h3_jump2me').click();";
			break;
	}

	
	?>
	<script>
	jQuery(document).ready(function(){
		toggle_ok_notok('#h3_check_jump2me', '<?php echo $check_jump2me ? 'ok' : 'notok' ; ?>' );
		toggle_ok_notok('#h3_check_wordpress', '<?php echo $check_wordpress ? 'ok' : 'notok' ; ?>' );
		<?php echo $script_expand; ?>
	});
	</script>	
	
	<div class="wrap">

	<div class="icon32" id="icon-plugins"><br/></div>
	<h2>Jump2.me para Wordpress</h2>
	
	<div id="y_logo">
		<div class="y_logo">
			<a href="http://jump2.me/"><img src="<?php echo $plugin_url; ?>/res/logo.png"></a>
		</div>
		<div class="y_text">
			<p><a href="http://jump2.me/">Jump2.me</a> é um serviço gratuito que pode ser usado para encurtar qualquer link e compartilhá-lo através do Twitter.</p>
			<p>Esse plugin é uma ponte que vincula seu blog ao <a href="http://jump2.me">Jump2.me</a> e ao <a href="http://jump2.me/twitter">Twitter</a> através da <a href="http://jump2.me/blog/api">API</a> que é usada para encurtar links e compartilhá-los.</p>
		</div>
	</div>
	
	<form method="post" action="options.php">
	<?php settings_fields('wp_jump2me_options'); ?>

	<h3>Configurações do Jump2.me <span class="h3_toggle expand" id="h3_jump2me">+</span> <span id="h3_check_jump2me" class="h3_check">*</span></h3>

	<div class="div_h3" id="div_h3_jump2me">
	<table class="form-table">

	<tr valign="top">
	<th scope="row">Chave da API<span class="mandatory">*</span></th>
	<td>

	<input type="text" class="y_longfield" id="y_path" name="jump2me[api_key]" value="<?php echo $jump2me['api_key']; ?>"/></br>
	Para obter a chave de acesso, acesse o <a href="http://jump2.me">Jump2.me</a>, efetue o <a href="http://jump2.me/jump2/login">login</a> e copie a chave no <a href="http://jump2.me/jump2/profile">perfil do usuário</a>.
	
	
	</td>
	</tr>
	</table>
	</div><!-- div_h3_jump2me -->
	
	
	<h3>Configurações do Wordpress <span class="h3_toggle expand" id="h3_wordpress">+</span> <span id="h3_check_wordpress" class="h3_check">*</span></h3> 

	<div class="div_h3" id="div_h3_wordpress">

	<h4>Quando gerar e compartilhar um link curto</h4> 
	
	<table class="form-table">

	<?php
	$types = get_post_types( array('publicly_queryable' => 1 ), 'objects' );
	foreach( $types as $type=>$object ) {
		$name = $object->labels->singular_name
		?>
		<tr valign="top">
		<th scope="row">Novo(a) <strong><?php echo $name; ?></strong> publicado(a)</th>
		<td>
		<input class="y_toggle" id="generate_on_<?php echo $type; ?>" name="jump2me[generate_on_<?php echo $type; ?>]" type="checkbox" value="1" <?php checked( '1', $jump2me['generate_on_'.$type] ); ?> /><label for="generate_on_<?php echo $type; ?>"> Gerar link curto</label><br/>
		<?php $hidden = ( $jump2me['generate_on_'.$type] == '1' ? '' : 'y_hidden' ) ; ?>
		<?php if( $type != 'attachment' ) { ?>
		<div id="y_show_generate_on_<?php echo $type; ?>" class="<?php echo $hidden; ?> generate_on_<?php echo $type; ?>">
			<input id="tweet_on_<?php echo $type; ?>" name="jump2me[tweet_on_<?php echo $type; ?>]" type="checkbox" value="1" <?php checked( '1', $jump2me['tweet_on_'.$type] ); ?> /><label for="tweet_on_<?php echo $type; ?>"> Compartilhar o link curto através do Twitter</label>
		</div>
		<?php } ?>
		</td>
		</tr>
	<?php } ?>

	</table>

	<h4>Formatação</h4> 

	<table class="form-table">

	<tr valign="top">
	<th scope="row">Formato da mensagem<span class="mandatory">*</span></th>
	<td><input id="tw_msg" name="jump2me[twitter_message]" type="text" size="50" value="<?php echo $jump2me['twitter_message']; ?>"/><br/>
	Esse é o modelo do <i>tweet</i>. O <i>plugin</i> substiuirá <tt>$T</tt> como o título do post e <tt>$U</tt> com o link curto gerado pelo <a href="http://jump2.me">Jump2.me</a>, respeitando o limite de 140 bytes do Twitter<br/>
	Exemplos (clique para copiar)<br/>
	<ul id="tw_msg_sample">
		<li><code class="tw_msg_sample">Novidades no <?php bloginfo();?>: $T $U</code></li>
		<li><code class="tw_msg_sample">Olha isso: $T $U</code></li>
		<li><code class="tw_msg_sample">$T: $U</code></li>
	</ul>
	<em>Atenção: mantenha o formato da mensagem o mais curto possível!</em>
	<h4 id="toggle_advanced_template">Formatação avançada</h4>
	<div id="advanced_template">
		Você pode usar os seguintes símbolos no formato da mensagem:
		<ul>
			<li><b><tt>$U</tt></b>: link curto</li>
			<li><b><tt>$T</tt></b>: título do post</li>
			<li><b><tt>$A</tt></b>: nome do autor</li>
			<li><b><tt>$A{metadado}</tt></b>: metadado do autor. Exemplo: $A{first_name}. Veja <a href="http://codex.wordpress.org/Function_Reference/get_userdata">get_userdata()</a> para detalhes.</li>
			<li><b><tt>$F{metadado}</tt></b>: metadado de campo customizado do post. Veja <a href="http://codex.wordpress.org/Function_Reference/get_post_meta">get_post_meta()</a> para detalhes.</li>
			<li><b><tt>$L</tt></b>: tags em texto plano e letras minúsculas (separadas por espaço se mais de uma, limitado a 3 tags)</li>
			<li><b><tt>$H</tt></b>: tags como #hashtags e letras minúsculas (separadas por espaço se mais de uma, limitado a 3 tags)</li>
			<li><b><tt>$C</tt></b>: categorias como texto plano e letras minúsculas (separadas por espaço se mais de uma, limitado a 3 categorias)</li>
			<li><b><tt>$D</tt></b>: categorias como #hashtags e letras minúsculas (separadas por espaço se mais de uma, limitado a 3 categorias)</li>
		</ul>
		Lembre-se que você está limitado a 140 bytes! Se sua formatação incluir muitos símbolos, a mensagem poderá ser truncada!
	</div>
	</td>
	</tr>

	</table>
	
	</div> <!-- div_h3_wordpress -->
	
	<?php
	$reset = add_query_arg( array('action' => 'reset'), menu_page_url( 'jump2me', false ) );
	$reset = wp_nonce_url( $reset, 'reset-jump2me' );
	?>

	<p class="submit">
	<input type="submit" class="button-primary y_submit" value="<?php _e('Salvar alterações') ?>" />
	<?php echo "<a href='$reset' id='reset-jump2me' class='submitdelete'>Limpar</a> todas as configurações"; ?>
	</p>
	
	<p><small><span class="mandatory">*</span> indica um campo obrigatório. Clique em <img src="<?php echo $plugin_url; ?>/res/expand.png" /> para expandir a seção de configuração.</small></p>

	</form>

	</div> <!-- wrap -->

	
	<?php	
}

// Adiciona metabox a página de edição
/*
function wp_jump2me_addbox() {
	$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post' ;
	add_meta_box( 'jump2mediv', 'Jump2.me', 'wp_jump2me_drawbox', $post_type, 'side', 'default' );
} */


// Produz a metabox
/*
function wp_jump2me_drawbox( $post ) {
	$type = $post->post_type;
	$status = $post->post_status;
	$id = $post->ID;
	$title = $post->post_title;
	
	// Se não publicado, só mostra uma mensagem.
	if ( $status != 'publish' ) {
		echo '<p>Dependendo da <a href="options-general.php?page=jump2me">configuração</a>, um link curto será gerado e/ou um tweet será publicado.</p>';
		return;
	}
	
	$shorturl = wp_jump2me_geturl( $id );
	// Verifica se já possui um link curto para o post
	if ( !$shorturl ) {
		echo '<p>O link curto para esse post anda não foi criado.</p>';
		return;
	}
	
	echo '<p><strong>Link curto</strong></p>';
	echo '<div id="jump2me-shorturl">';
	echo "<p>Esse é o link curto para esse(a) $type: <strong><a href='$shorturl'>$shorturl</a></strong></p>";
	echo '</div>';
}
*/
?>