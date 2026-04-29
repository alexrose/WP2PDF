<?php
/*
Plugin Name: WordPress 2 PDF
Plugin URI: https://alextrandafir.ro
Description: WordPress to PDF posts
Version: 1.1
Author: Alexandru Trandafir
Author URI: https://github.com/alexrose
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit;
}

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

require_once(__DIR__ . "/vendor/autoload.php");

class WP2PDF_Plugin
{
    public static function init(): void
    {
        add_action('post_row_actions', [self::class, 'add_row_action'], 10, 2);
        add_action('page_row_actions', [self::class, 'add_row_action'], 10, 2);
        add_action('admin_post_wp2pdf_post', [self::class, 'generate']);
    }

    // Add "Generate PDF" to post/pages.
    public static function add_row_action(array $actions, \WP_Post $post): array
    {
        if (!current_user_can('administrator')) {
            return $actions;
        }

        $actions['wp2pdf'] = sprintf(
            '<a href="%s">%s</a>',
            wp_nonce_url(
                sprintf('admin-post.php?action=wp2pdf_post&post_id=%d', $post->ID),
                'wp2pdf'
            ),
            esc_html__('Generate PDF', 'wp2pdf')
        );

        return $actions;
    }

    // Generate and download PDF
    public static function generate(): void
    {
        // Check permissions
        if (!current_user_can('administrator') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wp2pdf')) {
            wp_safe_redirect(admin_url('edit.php?error=unauthenticated'));
            exit();
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (!$post_id) {
            wp_safe_redirect(admin_url('edit.php?error=invalid_post'));
            exit();
        }

        $wpContent = get_post($post_id);

        // Validate post exists
        if (!$wpContent instanceof \WP_Post || !in_array($wpContent->post_status, ['publish', 'private', 'draft'], true)) {
            wp_safe_redirect(admin_url('edit.php?error=not_found'));
            exit();
        }

        $wpThumbnail = self::get_thumbnail($wpContent->ID);
        $htmlContent = self::get_pdf_html($wpContent, $wpThumbnail) . $wpContent->post_content;
        $filename = sanitize_file_name($wpContent->post_title) . '.pdf';

        $mPdf = new Mpdf(self::get_pdf_options());
        $mPdf->SetDisplayMode('fullpage');
        $mPdf->WriteHTML($htmlContent);
        $mPdf->Output($filename, "D");
        exit();
    }

    private static function get_pdf_options(): array
    {
        $defaultConfig    = (new ConfigVariables())->getDefaults();
        $fontDirs         = $defaultConfig['fontDir'];
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData         = $defaultFontConfig['fontdata'];

        return [
            'tempDir'              => __DIR__ . '/tmp',
            'fontDir'              => array_merge($fontDirs, [__DIR__ . '/fonts']),
            'fontdata'             => $fontData + [
                    'wp2pdf' => [
                        'R' => 'Wp2Pdf-Regular.ttf',
                    ],
                ],
            'default_font'         => 'calibri',
            'mode'                 => 'utf-8',
            'format'               => 'A5-P',
            'mirrorMargins'        => 1,
            'margin_left'          => 16,
            'margin_right'         => 12,
            'margin_top'           => 20,
            'margin_bottom'        => 6,
            'margin_header'        => 10,
            'margin_footer'        => 12.7,
            'list_auto_mode'       => 'mpdf',
            'list_marker_offset'   => '10mm',
            'list_indent_default_mpdf' => '10mm',
        ];
    }

    private static function get_thumbnail(int $postId): ?string
    {
        if ($wpThumb = get_field("thumb", $postId)) {
            return $wpThumb['sizes']['medium'] ?? null;
        }
        return null;
    }

    private static function get_pdf_html(\WP_Post $wpContent, ?string $wpThumbnail): string
    {
        $safeTitle     = esc_html($wpContent->post_title);
        $safeThumbnail = $wpThumbnail ? esc_url($wpThumbnail) : '';

        $thumbnailHtml = $safeThumbnail
            ? "<div class='thumbnail'><img src='{$safeThumbnail}' width='100%' height='100%' alt='' /></div>"
            : '';

        return "
            <style type='text/css'>
                body { margin: 0; padding: 0; font-size: 10pt; }
                ul { margin-top: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 0; }
                li { line-height: 22.7pt; margin: 0; padding: 0; }
                p { margin: 0; padding: 0; font-family: wp2pdf, serif; font-size: 14pt; line-height: 22.7pt; }
                p.header { padding-left: 21mm; font-family: wp2pdf, serif; font-size: 18pt; color: #333333; }
                p.is-style-text-label, p.is-style-text-label-2, figure { display: none; }
                .none { display: none; }
                .thumbnail { width: 20mm; height: 20mm; position: absolute; top: 16mm; right: 10mm; }
            </style>
            <htmlpageheader name='firstpage' class='none'>
                <p class='header'>{$safeTitle}</p>
            </htmlpageheader>
            <htmlpageheader name='otherpages' class='none'></htmlpageheader>
            <sethtmlpageheader name='firstpage' value='on' show-this-page='1' />
            <sethtmlpageheader name='otherpages' value='on' />
            {$thumbnailHtml}
        ";
    }
}

WP2PDF_Plugin::init();