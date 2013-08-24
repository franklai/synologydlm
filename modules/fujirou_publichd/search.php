<?php
if (!class_exists('FujirouCommon')) {
    if (file_exists(__DIR__.'/fujirou_common.php')) {
        require(__DIR__.'/fujirou_common.php');
    } else if (file_exists(__DIR__.'/../../include/fujirou_common.php')) {
        require(__DIR__.'/../../include/fujirou_common.php');
    }
}

class FujirouPublicHD {
    private $site = 'http://publichd.se';
    private $queryUrl = 'http://publichd.se/index.php?page=torrents&active=0&search=';

    public function __construct() {
    }

    public function prepare($curl, $query) {
        // http://publichd.se/index.php?page=torrents&active=0&search=star+trek&&order=5&by=2
        $url = $this->queryUrl . urlencode($query);
        curl_setopt($curl, CURLOPT_URL, $url);
    }

    private function getTableBody($response) {
        $prefix = '<tbody>';
        $suffix = '</tbody>';
        return FujirouCommon::getSubString($response, $prefix, $suffix);
    }

    private function getCategory($string) {
        $pattern = '/ alt="([^"]+)"/';

        return FujirouCommon::getFirstMatch($string, $pattern);
    }
    private function getTitle($string) {
        return trim(strip_tags(
            FujirouCommon::getSubString($string, '<a ', '</a>')
        ));
    }
    private function getPageLink($string) {
        $pattern = '/a href="([^"]+)"/';

        $link = FujirouCommon::decodeHTML(
            FujirouCommon::getFirstMatch($string, $pattern)
        );

        return sprintf("%s/%s", $this->site, $link);
    }
    private function getHash($string) {
        // magnet:?xt=urn:btih:RT7YVDZBI6ULE5DSSBQOMN2LGR6735EI&dn=Star+Trek
        $pattern = '/xt=urn:btih:([0-9A-Z]+)/';
        return FujirouCommon::getFirstMatch($string, $pattern);
    }
    private function getDownloadLink($string) {
        // <a href="download.php?id=8cff8a8f2147a8b274729060e6374b347dfdf488&amp;f=Star+Trek+Into+Darkness+2013+1080p+BrRip+Pimp4003+%28PimpRG%29.torrent">
        $pattern = '/a href="(download.php[^"]+)"/';
        $link = FujirouCommon::decodeHTML(
            FujirouCommon::getFirstMatch($string, $pattern)
        );

        return sprintf("%s/%s", $this->site, $link);
    }
    private function getDatetime($string) {
        $pattern = '/>([^<]+)</';
        $raw = FujirouCommon::getFirstMatch($string, $pattern);

        // DD/MM/YYYY, e.g. 20/08/2013
        $date = DateTime::createFromFormat('d/m/Y', $raw);
        return $date->format('Y-m-d H:i:s');
    }
    private function getSeeds($string) {
        $pattern = '/>([^<]+)</';
        return FujirouCommon::getFirstMatch($string, $pattern);
    }
    private function getLeechs($string) {
        $pattern = '/>([^<]+)</';
        return FujirouCommon::getFirstMatch($string, $pattern);
    }
    private function getSize($string) {
        $pattern = '/>([^<]+)</';
        return FujirouCommon::convertSize(
            FujirouCommon::getFirstMatch($string, $pattern)
        );
    }

    private function getInfoFromItem($item) {
        // parse string ($item) to get
        // title
        // download: link, we should grab hash id to magnet link
        // hash
        // size
        // page: link of this article
        // date
        // seeds
        // leechs
        // category
        $info = array();

        // skip non item tr
        if (FALSE === strpos($item, 'download.php?id=')) {
            echo "not item tr\n";
            return FALSE;
        }

        $tdArray = explode('<td', $item);
        // 0:
        // 1: category
        // 2: title, page
        // 3: magnet, download
        // 4: datetime
        // 5: seeds
        // 6: leechs
        // 7: publisher
        // 8: size

        $info['category'] = $this->getCategory($tdArray[1]);
        $info['title'] = $this->getTitle($tdArray[2]);
        $info['page'] = $this->getPageLink($tdArray[2]);
        $info['hash'] = $this->getHash($tdArray[3]);
        $info['download'] = $this->getDownloadLink($tdArray[3]);
        $info['datetime'] = $this->getDatetime($tdArray[4]);;
        $info['seeds'] = $this->getSeeds($tdArray[5]);;
        $info['leechs'] = $this->getLeechs($tdArray[6]);;
        $info['size'] = $this->getSize($tdArray[8]);
 
        return $info;
    }


    public function parse($plugin, $response) {
        // get table body first!
        $tbody = $this->getTableBody($response);

        $trArray = explode('<tr>', $tbody);

        $infoList = array();

	$count = 0;
        foreach ($trArray as $item) {
            $info = $this->getInfoFromItem($item);

            if ($info) {
                $plugin->addResult(
                    $info['title'], $info['download'], $info['size'],
                    $info['datetime'], $info['page'], $info['hash'],
                    $info['seeds'], $info['leechs'], $info['category']
                );

		$count += 1;
            }
        }
        return $count;
    }
}

if (!debug_backtrace()) {
    function init_curl() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

        return $curl;
    }

    class TestObj {
        function addResult($title, $download, $size,
                $datetime, $page, $hash,
                $seeds, $leechs, $category) {

            echo $title;
            echo "\n";
            echo "\t" .'datetime:'."\t". $datetime . "\n";
            echo "\t" .'download:'."\t". $download. "\n";
            echo "\t" .'size:'."\t". $size. "\n";
            echo "\t" .'seeds:'."\t". $seeds . "\n";
            echo "\t" .'leechs:'."\t". $leechs . "\n";
            echo "\t" .'page:'."\t". $page . "\n";
            echo "\t" .'category:'."\t". $category . "\n";
            echo "\t" .'hash:'."\t". $hash. "\n";
            echo "\n";
            echo "\n";
        }
    }

    $curl = init_curl();

    $module = 'FujirouPublicHD';
    $query = 'star trek';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance();
    $obj->prepare($curl, $query);

    $testObj = new TestObj();

    echo "curl_exec...\n";
    $response = curl_exec($curl);

    $total = $obj->parse($testObj, $response);
}

// vim: expandtab ts=4
