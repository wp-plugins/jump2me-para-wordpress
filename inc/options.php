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

// Valida opções de configuração
function wp_jump2me_sanitize( $in ) {
	//global $wp_jump2me;
	
	$in = array_map( 'esc_attr', $in);
	
	foreach( $in as $key=>$value ) {
		if( preg_match( '/^(generate|tweet)_on_/', $key ) ) {
			$in[$key] = ( $value == 1 ? 1 : 0 );
		}
	}
	
	$jump2me_template_dflt = '<div class="jump2me_connect"><p><strong>Usuários do Twitter</strong><br />Clique no botão abaixo para efetuar o login usando sua conta do Twitter.</p></div>';

	$jump2me_login_text_dflt = '<div class="jump2me_connect"><p><strong>Usuários do Twitter</strong><br />Clique no botão abaixo para efetuar o login usando sua conta do Twitter.</p></div><br/><br/>';

	$jump2me_tweet_this_text_dflt = 'Veja minha opinião sobre $T $U';
	
	if(empty($in['template']))
	{
	    $in['template'] = $jump2me_template_dflt;
	}
	if(empty($in['tweet_quote_template']))
	{
	    $in['tweet_quote_template'] = $jump2me_tweet_quote_template_dflt;
	}
	if(empty($in['login_text']))
	{
	    $in['login_text'] = $jump2me_login_text_dflt;
	}
	if(empty($in['tweet_this_text']))
	{
	    $in['tweet_this_text'] = $jump2me_tweet_this_text_dflt;
    }

	if(empty($in['user_login_suffix'])) {
          $in['user_login_suffix'] = '@jump2.me';
    }
	if(empty($in['add_to_comment_page']))
	{
	    $in['add_to_comment_page'] = 1;
	}
       
	if(empty($in['email_default'])) {
         //$sitedomain = wp_jump2me_get_domain();
		$sitedomain = 'jump2.me';
        $in['email_default'] = '%%username%%@'.$sitedomain;
     }

	if(empty($in['profile_images'])) {
        $in['profile_images'] = 'http://api.twitter.com/1/users/profile_image/%%username%%'; 
	}
	    
	if(empty($in['redirect'])) {
        $in['redirect'] = 'wp-admin/index.php';
     }

	if(empty($in['tweet_this'])) {
        $in['tweet_this'] = 0;
     }

	if(!empty($in['api_key'])) {
        $in['api_key'] = trim($in['api_key']);
     }

	
	return $in;
}

// Verifica se o plugin está configurado
function wp_jump2me_settings_are_ok( $check = 'overall' ) {
	global $wp_jump2me;

	$check_jump2me   = ( isset( $wp_jump2me['api_key'] ) && !empty( $wp_jump2me['api_key'] ) ? true : false );
	$check_wordpress = ( isset( $wp_jump2me['twitter_message'] ) && !empty( $wp_jump2me['twitter_message'] ) ? true : false );
	$check_button = true;
	$check_authentication = true;
	$check_comment = true;
	
	if( $check == 'overall' ) {
		$overall = $check_jump2me && $check_wordpress && $check_button && $check_authentication & $check_comment;
		return $overall;
	} else {
		return array( 'check_jump2me' => $check_jump2me, 'check_wordpress' => $check_wordpress, 'check_button' => $check_button , 'check_authentication' => $check_authentication , 'check_comment' => $check_comment );
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
	global $wpdb;
	
	$jump2me_template_dflt = '<div class="jump2me_connect"><p><strong>Usuários do Twitter</strong><br />Clique no botão abaixo para efetuar o login usando sua conta do Twitter.</p></div>';

	$jump2me_login_text_dflt = '<div class="jump2me_connect"><p><strong>Usuários do Twitter</strong><br />Clique no botão abaixo para efetuar o login usando sua conta do Twitter.</p></div><br/><br/>';

	$jump2me_tweet_this_text_dflt = 'Veja minha opinião sobre $T $U';
	
	$redirect = 'wp-admin/index.php';

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
		toggle_ok_notok('#h3_check_button', '<?php echo $check_button ? 'ok' : 'notok' ; ?>' );
		toggle_ok_notok('#h3_check_authentication', '<?php echo $check_button ? 'ok' : 'notok' ; ?>' );
		toggle_ok_notok('#h3_check_comment', '<?php echo $check_button ? 'ok' : 'notok' ; ?>' );
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

	<input type="text" class="y_longfield" id="y_path" name="jump2me[api_key]" value="<?php echo trim($jump2me['api_key']); ?>"/><br/>
	<span class="description">Para obter a chave de acesso, acesse o <a href="http://jump2.me">Jump2.me</a>, efetue o <a href="http://jump2.me/jump2/login">login</a> e copie a chave no <a href="http://jump2.me/jump2/profile">perfil do usuário</a>.</span>
	
	
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
	<span class="description">
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
	</span>
	</td>
	</tr>
	</table>
	
	</div> <!-- div_h3_wordpress -->
	
	<h3>Configurações do Botão Tweet <span class="h3_toggle expand" id="h3_button">+</span> <span id="h3_check_button" class="h3_check">*</span></h3> 

	<div class="div_h3" id="div_h3_button">
	    
		<!-- <div class="twitter_button" style="float:right; margin-left: 10px;">  -->
		<!-- <iframe src="http://jump2.me/services/button.php?url=http%3A%2F%2Fjump2.me%2FP&count=vertical&lang=pt_BRtext=Estou%20configurando%20o%20plugin%20Jump2.me%20para%20Wordpress%20no%20meu%20blog%3A" height="62" width="55" frameborder="0" scrolling="no" allowtransparency="true" /> -->
		<!-- </div> -->
		
		<h4>Exiba o botão Tweet para que os leitores divulguem seus posts diretamente no Twitter. Clique no botão ao lado e veja como funciona.</h4> 
		
		<table class="form-table">

		<tr valign="top">
		<th scope="row">Ativado</th>
		<td>
			<input type="radio" value="yes" <?php if ($jump2me['twitter_enable'] == 'yes') echo 'checked="checked"'; ?> name="jump2me[twitter_enable]" id="twitter_enable_yes" group="twitter_enable"/>
	        <label for="twitter_enable_yes">Sim</label>
	        <br/>
	        <input type="radio" value="no" <?php if ($jump2me['twitter_enable'] == 'no' || !$jump2me['twitter_enable']) echo 'checked="checked"'; ?> name="jump2me[twitter_enable]" id="twitter_enable_no" group="twitter_enable" />
	        <label for="twitter_enable_no">Não</label>
	        <span class="description"></span>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row">Tipo</th>
		<td>
			<input type="radio" value="vertical" <?php if ($jump2me['twitter_version'] == 'vertical') echo 'checked="checked"'; ?> name="jump2me[twitter_version]" id="twitter_version_twitter_vertical" group="twitter_version"/>
            <label for="twitter_version_twitter_vertical">Contador Vertical</label>
            <br/>
            <input type="radio" value="horizontal" <?php if ($jump2me['twitter_version'] == 'horizontal') echo 'checked="checked"'; ?> name="jump2me[twitter_version]" id="twitter_version_twitter_horizontal" group="twitter_version" />
            <label for="twitter_version_twitter_horizontal">Contador Horizontal</label>
			<br/>
            <input type="radio" value="none" <?php if ($jump2me['twitter_version'] == 'none') echo 'checked="checked"'; ?> name="jump2me[twitter_version]" id="twitter_version_twitter_nocount" group="twitter_version"/>
            <label for="twitter_version_twitter_nocount">Simples</label>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row">Exibição</span></th>
		<td>
			<input type="checkbox" value="1" <?php if ($jump2me['twitter_display_page'] == '1') echo 'checked="checked"'; ?> name="jump2me[twitter_display_page]" id="twitter_display_page" group="twitter_display"/>
            <label for="tm_display_page">Exibir o botão nas páginas e posts</label>
            <br/>
            <input type="checkbox" value="1" <?php if ($jump2me['twitter_display_front'] == '1') echo 'checked="checked"'; ?> name="jump2me[twitter_display_front]" id="twitter_display_front" group="twitter_display"/>
            <label for="tm_display_front">Exibir o botão na página inicial</label>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row">Posicionamento</span></th>
		<td>
			<select name="jump2me[twitter_where]">
        		<option <?php if ($jump2me['twitter_where'] == 'before') echo 'selected="selected"'; ?> value="before">Início do post</option>
        		<option <?php if ($jump2me['twitter_where'] == 'after') echo 'selected="selected"'; ?> value="after">Fim do post</option>
        		<option <?php if ($jump2me['twitter_where'] == 'beforeandafter') echo 'selected="selected"'; ?> value="beforeandafter">Início e fim do post</option>
        		<option <?php if ($jump2me['twitter_where'] == 'shortcode') echo 'selected="selected"'; ?> value="shortcode">Código embutido no conteúdo do post: [twitter]</option>
        		<option <?php if ($jump2me['twitter_where'] == 'manual') echo 'selected="selected"'; ?> value="manual">Manual (através de alteração no tema)</option>
        	</select><br/>
        	<span class="description">Posição do botão na página</span>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row">Estilo</th>
		<td>
			<input type="text" class="y_longfield" value="<?php echo htmlspecialchars($jump2me['twitter_style']); ?>" name="jump2me[twitter_style]" id="twitter_style" /><br/>
            <span class="description">Defina o estilo do DIV que envolve o botão. Esse parâmetro é útil para ajustar o posicionamento do botão em relação ao tema do blog.<br/>
				Exemplos (clique para copiar)<br/>
				<ul id="tw_style_sample">
					<li><code class="tw_style_sample">float: right; margin-left: 10px;</code></li>
					<li><code class="tw_style_sample">float: left; margin-right: 10px;</code></li>
				</ul>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row">via @(usuário do Twitter)</th>
		<td>
			<input type="text" value="<?php echo htmlspecialchars($jump2me['twitter_via']); ?>" name="jump2me[twitter_via]" id="twitter_via" /><br/>
            <span class="description">Esse usuário será mencionado no texto sugerido</span>
		</td>
		</tr>
		
		<tr>
        	<th scope="row" valign="top"><label for="twitter_lang">Idioma</label></th>
            <td>
            	<select name="jump2me[twitter_lang]" id="twitter_lang">
            		<option value="pt_BR" <?php if ($jump2me['twitter_lang'] == 'pt_BR') echo 'selected="selected"'; ?>>Português (Brasil)</option>
            	</select>
            </td>
        </tr>

		</table>
	
	</div> <!-- div_h3_button -->
	
	<h3>Configurações de Autenticação <span class="h3_toggle expand" id="h3_authentication">+</span> <span id="h3_check_authentication" class="h3_check">*</span></h3> 

	<div class="div_h3" id="div_h3_authentication">
	    
		<h4>Habilite a autenticação integrada com o Twitter. Dessa forma, os leitores do blog podem se registrar de uma forma mais prática e rápida.</h4> 
		
		<table class="form-table">

		<tr valign="top">
		<th scope="row">Ativado</th>
		<td>
			<input type="radio" value="1" <?php if ($jump2me['authentication_enable'] == '1') echo 'checked="checked"'; ?> name="jump2me[authentication_enable]" id="authentication_enable_yes" group="authentication_enable"/>
	        <label for="authentication_enable_yes">Sim</label>
	        <br/>
	        <input type="radio" value="0" <?php if ($jump2me['authentication_enable'] == '0' || !$jump2me['authentication_enable']) echo 'checked="checked"'; ?> name="jump2me[authentication_enable]" id="authentication_enable_no" group="authentication_enable" />
	        <label for="authentication_enable_no">Não</label><br />
	        <span class="description">Quando ativado, os usuários poderão registrar comentários usando o Twitter. Essa é uma excelente forma de estimular a participação dos leitores.</span>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">Exibir na página de login</th>
		<td>
			<input type="checkbox" value="1" <?php if ($jump2me['add_to_login_page'] == '1') echo 'checked="checked"'; ?> name="jump2me[add_to_login_page]" id="add_to_login_page" group="add_to_login_page"/>
            <label for="add_to_login_page">Exibir o botão de login na página padrão do Wordpress</label>
            <br/>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">Texto na página de login</th>
		<td>
			<textarea name='jump2me[login_text]' rows="5" cols="50"><?php echo (empty($jump2me['login_text']) ? $jump2me_login_text_dflt : $jump2me['login_text']); ?></textarea><br />
            <span class="description">Esse texto aparecerá acima do botão de login. Não remova a definição <i>class="jump2me_connect"</i></span>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">Redirecionamento</th>
		<td>
			<input type="text" value="<?php echo (empty($jump2me['redirect']) ? $redirect : $jump2me['redirect']); ?>" name="jump2me[redirect]" id="redirect" /><br/>
            <span class="description">O usuário será redirecionado para essa página depois de ser autenticado na página de login</span>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">Botão de autenticação</th>
		<td>
			<?php 
			if(count($btn_images) == 0)
            {
                $btn_images = jump2me_get_buttons();
            }
        	foreach($btn_images as $btn_image): ?>
				<input type="radio" value="<?php echo $btn_image ?>" <?php if ($jump2me['btn_choice'] == $btn_image) echo 'checked="checked"'; ?> name="jump2me[btn_choice]" id="btn_choice" group="btn_choice"/>
				<img src="<?php echo $btn_image ?>" alt="" /><br />
        	<?php endforeach; ?>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row">Posição do Botão (Opcional)</th>
		<td>
			<table>
			<tr>
			<td><label for="before_comment">Página de comentário</label></td><td><input type="text" value="<?php echo $jump2me['before_comment']; ?>" name="jump2me[before_comment]" id="before_comment" size="20" /></td>
			</tr>
			<tr>
			<td><label for="before_login">Página de login</label></td><td><input type="text" value="<?php echo $jump2me['before_login']; ?>" name="jump2me[before_login]" id="before_login" size="20" /></td>
			</tr>
			</table>
			<span class="description">Use essas opções para alterar onde o botão de login será exibido. Informe o ID de um elemento HTML na página e o botão será exibido antes desse elemento.</span>
		</td>
		</tr>
	   </table>
	   </div> <!-- div_h3_authentication -->
	
	<h3>Configurações dos Comentários <span class="h3_toggle expand" id="h3_comment">+</span> <span id="h3_check_comment" class="h3_check">*</span></h3> 

	<div class="div_h3" id="div_h3_comment">
	
	<h4>Integre o formulário de comentários do blog com o Twitter.</h4> 
	
	<table class="form-table">

	<tr valign="top">
	<th scope="row">Exibir botão de login</th>
	<td>
		<input type="checkbox" value="1" <?php if ($jump2me['add_to_comment_page'] == '1') echo 'checked="checked"'; ?> name="jump2me[add_to_comment_page]" id="add_to_comment_page" group="add_to_comment_page"/>
        <label for="add_to_comment_page">Permitir que os visitantes façam comentários autenticando-se através do Twitter</label><br/>
		<span class="description">Se marcado, o botão de login será exibido em cada página de comentário</span>	
	</td>
	</tr>
	<!-- Precisa de mais testes... Vai ser liberado na versão 1.6
	<tr valign="top">
	<th scope="row">Redirecionamento para página de edição do perfil</th>
	<td>
		<input type="checkbox" value="1" <?php if ($jump2me['comment_redirect'] == '1') echo 'checked="checked"'; ?> name="jump2me[comment_redirect]" id="comment_redirect" group="comment_redirect"/>
        <label for="comment_redirect">Exibir página de edição do perfil no Wordpress</label><br/>
		<span class="description">O redirecionamento será feito somente se o usuário não tiver alterado configurado o e-mail real</span>	
	</td>
	</tr>
	-->
	<tr valign="top">
	<th scope="row">Link do Autor</th>
	<td>
		<input type="checkbox" value="1" <?php if ($jump2me['use_twitter_profile'] == '1') echo 'checked="checked"'; ?> name="jump2me[use_twitter_profile]" id="use_twitter_profile" group="use_twitter_profile"/>
        <label for="use_twitter_profile">O link do autor aponta para o perfil do Twitter</label><br/>
		<span class="description">Se marcado, o link para o autor do comentário aponta para o Twitter. Por exemplo: <a href="http://twitter.com/luthiano" target="_blank">http://twitter.com/luthiano</a></span>	
	</td>
	</tr>
	<tr valign="top">
	<th scope="row">Texto na página de comentários</th>
	<td>
		<textarea name='jump2me[template]' rows="5" cols="50"><?php echo (empty($jump2me['template']) ? $jump2me_template_dflt : $jump2me['template']); ?></textarea>
	    <br/>
		<span class="description">Esse é o texto que aparece acima do botão de login na página de comentários. Não remova o texto <em>class="jump2me_connect"</em></span>	
	</td>
	</tr>
	<!-- Precisa de mais testes... Vai ser liberado na versão 1.6
	<tr valign="top">
	<th scope="row">Publicar comentário no Twitter</th>
	<td>
		<input type="checkbox" value="1" <?php if ($jump2me['tweet_this'] == '1') echo 'checked="checked"'; ?> name="jump2me[tweet_this]" id="tweet_this" group="tweet_this"/>
        <label for="tweet_this">Exibir opção para que os visitantes publiquem um link no Twitter quando submeterem um comentário</label><br/>
		<span class="description">Essa opção somente estará disponível se o usuário foi autenticado pelo Twitter.</span><br/>
		<input type='text' name='jump2me[tweet_this_text]' value='<?php echo (empty($jump2me['tweet_this_text']) ? $jump2me_tweet_this_text_dflt : $jump2me['tweet_this_text']); ?>' size="70" /><br/>		
	</td>
	-->
	</tr>
	
  </table>
	
	</div> <!-- div_h3_comment -->
	
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
function wp_jump2me_addbox() {
	$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post' ;
	add_meta_box( 'jump2mediv', 'Jump2.me', 'wp_jump2me_drawbox', $post_type, 'side', 'default' );
}


// Produz a metabox
function wp_jump2me_drawbox( $post ) {
	$type = $post->post_type;
	$status = $post->post_status;
	$id = $post->ID;
	$title = $post->post_title;
	
	// Se não publicado, só mostra uma mensagem.
	if ( $status != 'publish' ) {
		echo '<p><b>Dica:</b> Se você adicionar o campo personalizado <i>jump2me_keyword</i> antes de publicar o post, seu conteúdo será usado como palavra-chave para criação do link curto. É um forma bacana de tornar o texto do link mais amigável.</p>';
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
	echo '<p><strong>Estatísticas</strong></p>';
	echo '<div id="jump2me-stats">';
	echo "<p>Acesse as estatísticas de acesso aqui: <strong><a href='$shorturl+'>$shorturl+</a></strong></p>";
	echo '</div>';
}
			
?>