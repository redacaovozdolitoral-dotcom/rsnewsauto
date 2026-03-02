<?php
/**
 * Plugin Name: RS News Importer
 * Description: Importa notícias do feed RSNewsAuto para o WordPress.
 * Version: 1.0
 * Author: Studio RS Ilhabela
 */

if (!defined('ABSPATH')) exit;

define('RS_NEWS_FEED_BASE', 'https://admin.studiorsilhabela.com.br/feed/');

add_action('admin_menu', 'rs_news_importer_menu');
function rs_news_importer_menu() {
    add_options_page('RS News Importer', 'RS News Importer', 'manage_options', 'rs-news-importer', 'rs_news_importer_page');
}

function rs_news_importer_page() {
    $client_id = get_option('rs_news_client_id', '');
    $saved = false;
    if (isset($_POST['rs_news_save']) && check_admin_referer('rs_news_importer')) {
        $client_id = sanitize_text_field($_POST['rs_news_client_id'] ?? '');
        update_option('rs_news_client_id', $client_id);
        $saved = true;
    }
    ?>
    <div class="wrap">
        <h1>RS News Importer</h1>
        <?php if ($saved): ?><div class="notice notice-success"><p>Configuração salva!</p></div><?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('rs_news_importer'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="rs_news_client_id">ID do Cliente</label></th>
                    <td>
                        <input type="text" id="rs_news_client_id" name="rs_news_client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text" placeholder="ex: vozdolitoral">
                        <p class="description">ID do seu portal no RSNewsAuto (ex: vozdolitoral, bocanotrombonelitoral)</p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="rs_news_save" class="button button-primary" value="Salvar"></p>
        </form>
        <hr>
        <h2>Importar agora</h2>
        <?php if ($client_id): ?>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=rs-news-importer&action=import')); ?>" class="button">Importar notícias do feed</a></p>
        <?php else: ?>
            <p class="description">Configure o ID do cliente acima para habilitar a importação.</p>
        <?php endif; ?>
        <?php
        if (isset($_GET['action']) && $_GET['action'] === 'import' && $client_id) {
            rs_news_do_import($client_id);
        }
        ?>
    </div>
    <?php
}

function rs_news_do_import($client_id) {
    $feed_url = RS_NEWS_FEED_BASE . $client_id;
    $feed = fetch_feed($feed_url);
    if (is_wp_error($feed)) {
        echo '<div class="notice notice-error"><p>Erro ao buscar feed: ' . esc_html($feed->get_error_message()) . '</p></div>';
        return;
    }
    $items = $feed->get_items(0, 20);
    $imported = 0;
    foreach ($items as $item) {
        $link = $item->get_permalink();
        $title = $item->get_title();
        $content = $item->get_content();
        $date = $item->get_date('Y-m-d H:i:s');
        // Evitar duplicatas: verificar por link (mais confiável que título)
        $existing = get_posts([
            'post_type' => 'post',
            'meta_key' => '_rs_news_source',
            'meta_value' => esc_url($link),
            'posts_per_page' => 1,
        ]);
        if (!empty($existing)) continue;
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_date' => $date,
            'post_type' => 'post',
        ]);
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_rs_news_source', esc_url($link));
            $imported++;
        }
    }
    echo '<div class="notice notice-success"><p>Importadas ' . $imported . ' notícias.</p></div>';
}
