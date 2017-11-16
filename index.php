<form method="POST">
    <input name="url" type="text"
           value="<?= isset($_REQUEST['url']) ? $_REQUEST['url'] : 'https://www.meb100.ru/navigation#tab_organizations'; ?>"/>
    <input name="regionNumber" type="text" value="3"/> 27
    <input type="submit" value="Пошел">
</form>
<?php

ini_set('max_execution_time', '1800');

include 'simple_html_dom.php';

function request($url, $post = 0)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url); // отправляем на
    curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.google.ru');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут4
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//    curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookie.txt'); // сохранять куки в файл
//    curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookie.txt');
    curl_setopt($ch, CURLOPT_POST, $post !== 0); // использовать данные в post
    if ($post)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

class parser
{
    function __construct()
    {
        if (isset($_POST['url'])) {
            $this->parse($_POST['url']);
        }
    }

    function parse($url)
    {
        $regionNumber = $_POST['regionNumber'];
        echo $regionNumber . 'Region<br>';

        $dbIS = mysqli_connect('localhost', 'root', '', "information_schema");
        $dbParser = mysqli_connect('localhost', 'root', '', "parser");
        mysqli_set_charset($dbParser, 'utf8');

        /*$sql = 'SELECT AUTO_INCREMENT FROM information_schema.tables WHERE TABLE_NAME = \'josrr_users\'';
        $result = mysqli_query($dbIS, $sql);
        $row = mysqli_fetch_assoc($result);
        $idUser = $row['AUTO_INCREMENT'];
        echo $idUser . ' USER ID<br><hr>';*/

        $url = $this->readUrl($url);

        $data = str_get_html(request($url));

        $i = 0;

        if ($data->innertext != '' and count($data->find('#tab-organizations h3.title-town a'))) {
            foreach ($data->find('#tab-organizations h3.title-town a') as $region) {

                if ($i == $regionNumber) {
                    $regionNameDB = trim($region->plaintext);

                    echo "<h2>ЭТО РЕГИОН: " . $regionNameDB . "</h2><br/>";

                    $sql = 'SELECT AUTO_INCREMENT FROM information_schema.tables WHERE TABLE_NAME = \'josrr_regions\'';
                    $result = mysqli_query($dbIS, $sql);
                    $row = mysqli_fetch_assoc($result);
                    $aiRegions = $row['AUTO_INCREMENT'];
                    echo $aiRegions . ' REGION ID<br><hr>';

                    $sql = "INSERT INTO `josrr_regions` (`id`, `title`, `parent_id`) VALUES (NULL, '" . $regionNameDB . "', '1')";
                    mysqli_query($dbParser, $sql);

                    $townUl = $data->find('#tab-organizations ul.list-towns', $i);

                    if (count($townUl->find('li a'))) {
                        foreach ($townUl->find('li a') as $town) {

                            $sql = 'SELECT AUTO_INCREMENT FROM information_schema.tables WHERE TABLE_NAME = \'josrr_regions\'';
                            $result = mysqli_query($dbIS, $sql);
                            $row = mysqli_fetch_assoc($result);
                            $aiTown = $row['AUTO_INCREMENT'];
                            echo $aiTown . ' TOWN ID<br><hr>';

                            //echo "ССЫЛКА НА ГОРОД https://www.meb100.ru" . $town->href . "<br/>";
                            $townNameDB = trim($town->plaintext);

                            echo "<h3>ЭТО ГОРОД: " . $townNameDB . "</h3><br/>";

                            $sql = "INSERT INTO `josrr_regions` (`id`, `title`, `parent_id`) VALUES (NULL, '" . $townNameDB . "', '" . $aiRegions . "')";
                            mysqli_query($dbParser, $sql);
                            /* _____________________________________________________________________________________________________ */

                            for ($t = 1; ; $t++) {
                                $t1 = $t + 1;
                                $data1 = str_get_html(request("https://www.meb100.ru" . $town->href . '?page=' . $t));//Страница города

                                if ($data1->innertext != '' and count($data1->find('a.ajax-factory-link'))) {

                                    foreach ($data1->find('a.ajax-factory-link') as $a) {

                                        $factoryLink = $a->href;

                                        $ii = 1;

                                        echo '<h1>--- ' . $ii . ' ---</h1><hr>';
                                        $factoryLink100 = "https://www.meb100.ru" . $factoryLink;
                                        echo "<h4>ЭТО ССЫЛКА НА ФАБРИКУ: " . $factoryLink100 . "</h4><br/>";

                                        for ($p = 1; ; $p++) {

                                            $p1 = $p + 1;

                                            if ($p == 1) {
                                                $sql = 'SELECT AUTO_INCREMENT FROM information_schema.tables WHERE TABLE_NAME = \'josrr_users\'';
                                                $result = mysqli_query($dbIS, $sql);
                                                $row = mysqli_fetch_assoc($result);
                                                $idUser = $row['AUTO_INCREMENT'];
                                                echo $idUser . ' USER ID<br><hr>';
                                                $dataItem = str_get_html(request($this->readUrl($factoryLink)));

                                                $item['title'] = count($dataItem->find('h1')) ? trim($dataItem->find('h1', 0)->plaintext) : '';

                                                $item['logo'] = count($dataItem->find('div.logo-organization img')) ? $dataItem->find('div.logo-organization img', 0) : '';
                                                $item['address'] = count($dataItem->find('#content__address')) ? trim($dataItem->find('#content__address', 0)->plaintext) : '';
                                                $item['phone'] = count($dataItem->find('div.content-line')) ? trim($dataItem->find('div.content-line', 0)->plaintext) : '';
                                                $item['site'] = count($dataItem->find('div.content-line a')) ? trim($dataItem->find('div.content-line a', 0)->plaintext) : '';
                                                $item['description'] = count($dataItem->find('div.shot-description')) ? trim($dataItem->find('div.shot-description', 0)->plaintext) : '';

                                                preg_match_all('/\W\d{11}/', $item['phone'], $item['phone']);

                                                $img = file_get_contents($item['logo']->src);
                                                $logoFileName = 'images/logos/logo' . $idUser . '.png';
                                                file_put_contents($logoFileName, $img);

                                                echo '<hr>ЭТО АЛИАС: ' . $this->makeUrlCode($item['title']) . '<hr>';
                                                echo $item['title'] . '<hr>';
                                                echo $item['logo'] . '<hr>';
                                                echo $item['address'] . '<hr>';
                                                print_r($item['phone']);
                                                echo '<hr>';
                                                echo $item['site'] . '<hr>';
                                                echo $item['logo']->src . '<hr>';
                                                echo $item['description'] . '<br><hr> ';

                                                $sql = "INSERT INTO `josrr_users` (`id`, `name`, `username`, `email`, `registerDate`, `lastvisitDate`) VALUES (NULL, '" . $item['title'] . "', 'id7" . $idUser . "', '" . $idUser . "@test.ru', now(), now())";
                                                mysqli_query($dbParser, $sql);

                                                $sql = "INSERT INTO `josrr_cck_store_item_users` (`id`, `cck`, `about_me`, `avatar`, `address1`, `region`, `country`,`website`,`website100`) VALUES ('" . $idUser . "', 'pf_user', '" . $item['description'] . "', '" . $logoFileName . "', '" . $item['address'] . "', '" . $aiTown . "', 'RU', '{\"link\":\"" . $item['site'] . "\"}', '" . $factoryLink100 . "')";
                                                mysqli_query($dbParser, $sql);

                                                $sql = "INSERT INTO `josrr_cck_core` (`id`, `cck`, `pk`, `pkb`, `storage_location`, `author_id`, `parent_id`, `store_id`,`download_hits`,`date_time`) VALUES (NULL , 'pf_user', '" . $idUser . "', '2', 'joomla_user', '" . $idUser . "', '0', '0', '0', now())";
                                                mysqli_query($dbParser, $sql);

                                                foreach ($item['phone'][0] as $phone) {
                                                    $sql = "INSERT INTO `josrr_user_phone` (`id`, `user_id`, `phone`) VALUES (NULL, '" . $idUser . "', '" . $phone . "')";
                                                    mysqli_query($dbParser, $sql);
                                                }
                                            } else {
                                                $dataItem = str_get_html(request($this->readUrl($factoryLink . '?page=' . $p)));
                                            }

                                            foreach ($dataItem->find('a.ajax-preview') as $a) {

                                                $dataProduct = str_get_html(request($this->readUrl($a->href)));

                                                $itemProduct['title'] = count($dataProduct->find('h1')) ? trim($dataProduct->find('h1', 0)->plaintext) : '';
                                                $itemProduct['image'] = count($dataProduct->find('div.image-preview > a > img')) ? $dataProduct->find('div.image-preview > a > img', 0) : '';
                                                $itemProduct['description'] = count($dataProduct->find('div.product-description > p')) ? trim($dataProduct->find('div.product-description > p', 0)->plaintext) : '';


                                                echo '<br/><br/>' . $itemProduct['title'] . '<br/>';
                                                echo '<br/><br/>' . $itemProduct['description'] . '<br/>';
                                                //echo $itemProduct['image'] . '<br/><hr/> ';
                                                $imgProduct = file_get_contents($itemProduct['image']->src);
                                                $imgFileName = 'images/products/u' . $idUser . 'p' . $ii . '.png';
                                                file_put_contents($imgFileName, $imgProduct);

                                                $sql = "INSERT INTO `josrr_user_product` (`id`, `title`, `user_id`, `product_desc`, `product_image`, `link100`) VALUES (NULL, '" . $itemProduct['title'] . "', '" . $idUser . "', '" . $itemProduct['description'] . "', '" . $imgFileName . "', 'https://meb100.ru" . $a->href . "')";
                                                mysqli_query($dbParser, $sql);

                                                $sql = "INSERT INTO `josrr_cck_core` (`id`, `cck`, `pk`, `pkb`, `storage_location`, `storage_table`, `author_id`, `parent_id`, `store_id`,`download_hits`,`date_time`) VALUES (NULL , 'pf_product', '" . $idUser . "', '0', 'free', '#__user_product', '" . $idUser . "', '0', '0', '0', now())";
                                                mysqli_query($dbParser, $sql);

                                                $ii++;

                                                //break;
                                            }

                                            //break;

                                            echo "<hr>";

                                            if (!count($dataItem->find('a[href=?page=' . $p1 . ']'))) {
                                                echo 'STOP!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!';

                                                break;
                                            }

                                        }
                                        break;
                                    }

                                }
                                if (!count($data1->find('a[href=?page=' . $t1 . ']'))) {
                                    echo 'STOP!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!';
                                    break;
                                }
                            }
                        }

                    }
                }
                $i++;
            }
        }

        $data->clear();
        unset($data);

    }

    function printresult()
    {
        /* foreach($this->result as $item){
        echo '<h2>'.$item['title'].'</h2>';
        echo '<h3>'.$item['adres'].'</h3>';
        echo '<p style="margin:20px 0px;background:#eee; padding:20px;">'.$item['text'].'</p>';
        }; */
        exit();
    }

    function makeUrlCode($str)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V',
            'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
            'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'I', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R',
            'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        );
        $str = strtolower(strtr($str, $converter));
        return trim(preg_replace('/[^-a-z0-9_]+/', '-', $str), "-");
    }

    var $protocol = '';
    var $host = '';
    var $path = '';

    function readUrl($url)
    {
        $urldata = parse_url($url);
        if (isset($urldata['host'])) {
            if ($this->host and $this->host != $urldata['host'])
                return false;

            $this->protocol = $urldata['scheme'];
            $this->host = $urldata['host'];
            $this->path = $urldata['path'];
            return $url;
        }

        if (preg_match('#^/#', $url)) {
            $this->path = $urldata['path'];
            return $this->protocol . '://' . $this->host . $url;
        } else {
            if (preg_match('#/$#', $this->path))
                return $this->protocol . '://' . $this->host . $this->path . $url;
            else {
                if (strrpos($this->path, '/') !== false) {
                    return $this->protocol . '://' . $this->host . substr($this->path, 0, strrpos($this->path, '/') + 1) . $url;
                } else
                    return $this->protocol . '://' . $this->host . '/' . $url;
            }
        }
    }
}

$pr = new Parser();
/* $pr->printresult(); */