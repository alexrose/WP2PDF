<?php

namespace App;

use Exception;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\MpdfException;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../../wp-load.php';

class convert
{
    public const ALLOWED_HOSTS = ["domain.tld", "www.domain.tld"];

    /**
     * @return string
     * @throws Exception
     */
    protected function getUrl()
    {
        $url = parse_url(urldecode($_GET['source']));
        if (!in_array($url["host"], self::ALLOWED_HOSTS)) {
            throw new Exception("URL not available.");
        }

        return $url["path"];
    }

    /**
     * @param string $url
     * @return object
     * @throws Exception
     */
    protected function getPost(string $url)
    {
        $wpContent = get_posts(array('name' => $url));

        if (!count($wpContent)) {
            throw new Exception("Post not found.");
        }
        return $wpContent[0];
    }

    /**
     * @param int $postId
     * @return string|null
     */
    protected function getThumbnail(int $postId)
    {
        if ($wpThumb = get_field("thumb", $postId)) {
            return $wpThumb['sizes']['receptar-banner'];
        }

        return null;

    }

    /**
     * @return array
     */
    protected function getPdfOptions()
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

    /**
     * @param object $wpContent
     * @param string|null $wpThumbnail
     * @return string
     */
    protected function getPdfHelperHtml(object $wpContent, ?string $wpThumbnail)
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
                <p class='header' >".$wpContent->post_title."</p>
            </htmlpageheader>
            <htmlpageheader name='otherpages' class='none'></htmlpageheader>
            
            <sethtmlpageheader name='firstpage' value='on' show-this-page='1' />
            <sethtmlpageheader name='otherpages' value='on' />
            
            <div class='thumbnail'><img src='".$wpThumbnail."' width='100%' height='100%' alt='' /></div>
        ";
    }

    /**
     * @throws MpdfException
     * @throws Exception
     */
    public function generate()
    {
        $wpContent = $this->getPost($this->getUrl());
        $wpThumbnail = $this->getThumbnail($wpContent->ID);
        $mPdf = new Mpdf($this->getPdfOptions());
        $mPdf->SetDisplayMode('fullpage');
        $mPdf->WriteHTML($this->getPdfHelperHtml($wpContent, $wpThumbnail) . $wpContent->post_content);
        //$mPdf->Output(sprintf("%s.pdf", $wpContent->post_name), "D");
        $mPdf->Output();
    }
}

$pdf = new convert();
try {
    $pdf->generate();
} catch (Exception $e) {
}
die();