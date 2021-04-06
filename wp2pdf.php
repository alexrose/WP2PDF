<?php
/*
Plugin Name: WordPress 2 PDF
Plugin URI: https://alextrandafir.ro
Description: Wordpress to pdf posts
Version: 1.0
Author: Alexandru Trandafir
Author URI: alextrandafir.ro
License: GPLv2 or later
*/

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

require_once(__DIR__ . "/vendor/autoload.php");

add_action('post_row_actions', 'add_wp2pdf', 10, 2);
add_action('admin_post_wp2pdf_post', 'wp2pdf_generate');

function add_wp2pdf($actions, $post)
{
    if (!current_user_can('administrator')) {
        header('Location:' . $_SERVER["HTTP_REFERER"] . '?error=unauthenticated');
        exit();
    }

    $actions['wp2pdf'] = sprintf('<a href="%s">%s</a>',
        wp_nonce_url(sprintf('admin-post.php?action=wp2pdf_post&post_id=%d', $post->ID), 'wp2pdf'),
        __('Generate PDF', 'wp2pdf')
    );

    return $actions;
}

function wp2pdf_generate()
{
    if (!current_user_can('administrator') || !wp_verify_nonce( $_GET['_wpnonce'], 'wp2pdf')) {
        header('Location:' . $_SERVER["HTTP_REFERER"] . '?error=unauthenticated');
        exit();
    }

    $wpContent = get_post((int)$_REQUEST['post_id']);
    $wpThumbnail = getThumbnail($wpContent->ID);
    $htmlContent = getPdfHelperHtml($wpContent, $wpThumbnail) . $wpContent->post_content;

    $mPdf = new Mpdf(getPdfOptions());
    $mPdf->SetDisplayMode('fullpage');
    $mPdf->WriteHTML($htmlContent);
    $mPdf->Output(sprintf("%s.pdf", $wpContent->post_title), "D");
    //$mPdf->Output();
}


function getPdfOptions()
{
    // fonts defaults
    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    return [
        'tempDir' => __DIR__ . '/tmp',
        'fontDir' => array_merge($fontDirs, [__DIR__ . '/fonts']),
        'fontdata' => $fontData + [
                'wp2pdf' => [
                    'R' => 'Wp2Pdf-Regular.ttf',
                ]
            ],
        'default_font' => 'calibri',
        'mode' => 'utf-8',
        'format' => 'A5-P',
        'mirrorMargins' => 1,
        'margin_left' => 16,
        'margin_right' => 12,
        'margin_top' => 20,
        'margin_bottom' => 6,
        'margin_header' => 10,
        'margin_footer' => 12.7,
        'list_auto_mode' => 'mpdf',
        'list_marker_offset' => '10mm',
        'list_indent_default_mpdf' => '10mm',
    ];
}

function getThumbnail(int $postId)
{
    if ($wpThumb = get_field("thumb", $postId)) {
        return $wpThumb['sizes']['receptar-banner'];
    }

    return null;
}

function getPdfHelperHtml(object $wpContent, ?string $wpThumbnail)
{
    return "
            <style type='text/css'>
                body { margin: 0; padding: 0; font-size: 10pt; }
                ul { margin-top: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 0; }
                li { line-height: 22.7pt; margin: 0; padding: 0; }
                p { margin: 0; padding: 0;  font-family: wp2pdf,serif; font-size: 14pt; line-height: 22.7pt; }
                p.header { padding-left: 21mm; font-family: wp2pdf, serif; font-size: 18pt; color: #333333; }
                .none { display: none; }
                .thumbnail {width: 20mm; height: 20mm; position: absolute; top: 16mm; right: 10mm; }
            </style>

            <htmlpageheader name='firstpage' class='none'>
                <p class='header' >" . $wpContent->post_title . "</p>
            </htmlpageheader>
            <htmlpageheader name='otherpages' class='none'></htmlpageheader>

            <sethtmlpageheader name='firstpage' value='on' show-this-page='1' />
            <sethtmlpageheader name='otherpages' value='on' />

            <div class='thumbnail'><img src='" . $wpThumbnail . "' width='100%' height='100%' alt='' /></div>
        ";
}
