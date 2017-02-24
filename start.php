<?php
/**
 * 爬取廖雪峰网站教程
 */
define('HTML_TO_PDF','d:\wkhtmltopdf32.exe');
ini_set('memory_limit','1024m');

require './vendor/autoload.php';

use GuzzleHttp\Client;
use phpQuery\phpQuery;

$py = 'http://www.liaoxuefeng.com/wiki/0014316089557264a6b348958f449949df42a6d3a2e542c000';
$js = 'http://www.liaoxuefeng.com/wiki/001434446689867b27157e896e74d51a89c25cc8b43bdb3000';

$config = [
    'url'=> $js,
    'tmpDir'=>'',
    'pdfSavePath'=>'./pdf/',
    'pdfFileName'=>'test.pdf',
];

(new makePdf($config))->start();

class makePdf
{

    const BASE_URL = 'http://www.liaoxuefeng.com';

    protected $pdfSaveDir = 'E:\pdf';

    protected $menus = [];

    protected $tmpDir = './html/';

    protected $startUrl;

    protected $pdfSavePath = './pdf/';

    protected $pdfFileName = '';

    /**
     * makePdf constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        foreach ($config as $key =>$val)
        {
            if(property_exists($this,$key)){
                $this->$key = $val;
            }
        }

        if(empty($this->startUrl)){
            exit('start url can not be enmty!');
        }
        if(empty($this->tmpDir)){
            exit('tmp dir can not be enmty!');
        }elseif (!is_dir($this->tmpDir)){
            mkdir($this->tmpDir);
        }

        if(empty($this->pdfSavePath)){
            exit('pdf save path can not be enmty!');
        }elseif (!is_dir($this->pdfSavePath)){
            mkdir($this->pdfSavePath);
        }

        if(empty($this->pdfFileName)){
            $this->pdfFileName = date('Y-m-d H:i:s').'.pdf';
        }
    }

    public function start()
    {
        $dir = $this->pdfSaveDir . DIRECTORY_SEPARATOR . $this->tmpDir . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $this->tmpDir = $dir;
        $this->getMenus();
        $this->getContends();
        $this->createPdf();
    }

    protected function getMenus()
    {
        $html = $this->getPages($this->startUrl);
        phpQuery::newDocument($html);
        $tags = phpQuery::pq('div.x-sidebar-left-content .uk-nav-side a');
        foreach ($tags as $tag) {
            $style = $tag->parentNode->getAttribute('style');
            $this->menus[] = [
                $tag->getAttribute('href'),
                $tag->nodeValue,
                $style=='margin-left:1em;' ? 1:2
            ];
        }
        echo 'totalPage:'.count($tags).PHP_EOL;
    }

    protected function getContends()
    {
        foreach ($this->menus as $i => $item) {
            $htmlSrc = $this->tmpDir . $i . '.html';
            if (file_exists($htmlSrc)) {
                continue;
            }
            echo $i.'=>';
            $html = $this->getPages(self::BASE_URL . $item[0]);
            phpQuery::newDocument($html);
            $content = phpQuery::pq('div.x-wiki-content')->html();
            if(empty($content)){
                exit('failed');
            }
            file_put_contents($htmlSrc,$content);
        }
    }

    /**
     * @var Client
     */
    protected $http;

    protected function getPages($url)
    {
        echo $url . PHP_EOL;
        if (empty($this->http)) {
            $this->http = new Client();
        }
        return $this->http->get($url)->getBody();
    }

    protected function createPdf()
    {
        $html = <<<EOF
<html>
<head>
<title>book</title>
<style>
body{font-size:24px;}
</style>
<head>
<body>
EOF;
        $tmpFile = $this->pdfSaveDir.DIRECTORY_SEPARATOR.'tmp.html';
        file_put_contents($tmpFile,$html);
        foreach ($this->menus as $i=>$item){
            echo $i.PHP_EOL;
            $htmlSrc = $this->tmpDir . $i . '.html';
            $page = '<p><h'.$item[2].'>'.$item[1].'</h'.$item[2].'></p>';
            $page .= file_get_contents($htmlSrc);
            $page = str_replace('src="/','src="http://www.liaoxuefeng.com/',$page);
            file_put_contents($tmpFile,$page,FILE_APPEND );
        }
        $html .='</body></html>';
        file_put_contents($tmpFile,$html,FILE_APPEND);

        $cmd =HTML_TO_PDF. ' --outline '.$tmpFile.' --encode utf8 '.$this->pdfSavePath.DIRECTORY_SEPARATOR.$this->pdfFileName;
        echo $cmd.PHP_EOL;
        echo shell_exec($cmd);
    }

}