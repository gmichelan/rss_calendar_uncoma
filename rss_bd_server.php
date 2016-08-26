<?php

/* Métodos para cargar noticias rss en bd utilizando postgres y PHP */
//header("Access-Control-Allow-Origin: *");
include 'conexiondb.php';
function cargarFeed() {
    $urls_rss = consulta("SELECT * FROM fuente;","dbname = FeedUncoma");
    while ($row = pg_fetch_row($urls_rss))  {
        $rss_tags = array(
            'title',
            'link',
            'description',
            'author',
            'pubDate'
        );
        $rss_item_tag = 'item';
        $rss_url = $row[2];
        $rssfeed = rss_to_array($rss_item_tag, $rss_tags, $rss_url);
 
        foreach ($rssfeed AS $arreglo) {
            $titulo = $arreglo['title'];
            $copete = html_entity_decode(strip_tags($arreglo['description']));
            $link = $arreglo['link'];
            $fecha = strftime("%Y-%m-%d %H:%M:%S", strtotime($arreglo['pubDate']));
            $autor = $arreglo['author'];
            //preg_match_all('/<img[^>]+>/i',$arreglo['description'], $stringImg); 
            preg_match_all('/src=("[^"]*")/i',$arreglo['description'], $stringImg); //obtengo solo los src de la descripción
            preg_match_all('/("[^"]*")/i', $stringImg[0][0], $matches);//obtengo el link del primer src de la descripción
            //print_r($matches);
            //$imagen=$matches[0][0];
            $imagen=str_replace('"','',$matches[0][0]);  //quito las comillas dobles del link de la imagen
            //print_r($imagen);
            $prueba= consulta("SELECT id_noticia FROM noticia WHERE titulo ='$titulo' and fecha='$fecha' and id_fuente=$row[0];", "dbname = FeedUncoma");
            $row2=pg_fetch_row($prueba);
            if(empty($row2[0])){
                consulta("INSERT INTO noticia (titulo, copete, link, fecha,  autor, id_fuente, imagen) VALUES ( '$titulo', '$copete', '$link', '$fecha', '$autor', $row[0],'$imagen')", $dbname="dbname = FeedUncoma");
            }

            //consulta("INSERT INTO noticia (titulo, copete, link, fecha,  autor, id_fuente) VALUES ( '$titulo', '$copete', '$link', '$fecha', '$autor', $row[0])", $dbname="dbname = Feed_UNCo");
        }
    }
    pg_free_result($urls_rss);
}

/*
 * Pasa los rss obtenidos a un arreglo
 */

function rss_to_array($tag, $array, $url) {
    $doc = new DOMDocument();
    $doc->load($url);
    $rss_array = array();
    $items = array();
    foreach ($doc->getElementsByTagName($tag) AS $node) {
        foreach ($array AS $key => $value) {
            if ($value !== 'description') {
                $items[$value] = $node->getElementsByTagName($value)->item(0)->nodeValue;
            } else {
                $cadena = strip_tags($node->getElementsByTagName($value)->item(0)->nodeValue, '<span><div><img>');
                $items[$value] = $cadena;
            }
        }
        array_push($rss_array, $items);
    }
    return $rss_array;
}

/*
 * Muestra los 3 últimos rss obtenidos de cada página
 */

function mostrarFeed() {
    $muestra = "";
    $fuentes = consulta("SELECT id_fuente, nombre, url, pagina FROM fuente;", $dbname="dbname = FeedUncoma");
    while ($row = pg_fetch_row($fuentes)) {
        $noticias = consulta("SELECT n.id_noticia, n.titulo, n.copete, 
         n.link, n.fecha, n.autor FROM noticia as n WHERE n.id_fuente=" . $row[0] . " ORDER BY  n.id_noticia DESC LIMIT  3", $dbname="dbname = FeedUncoma");
        $muestra.="<h2><a href='" . $row[3] . "'>" . $row[1] . "</a></h2>";
        while ($row1 = pg_fetch_row($noticias)) {
            $muestra.= "<h4>" . $row1[1] . "</h4><p>" . $row1[2] . "<br>Autor:" . $row1[5] . "</p><a href='" . $row1[3] . "'>Ver más</a>";
        }
        pg_free_result($noticias);
    }
    pg_free_result($fuentes);
    echo $muestra;
}

/*
 * Devuelve la consulta de la bd en JSON
 */

function consultaJson() {
    $json = array();
    $fuentes = consulta("SELECT id_fuente, nombre, url, pagina FROM fuente;", $dbname="dbname = FeedUncoma");
    $i = 0;
    while ($row = pg_fetch_array($fuentes)) {
        //print_r($row);
        $json[$i] = $row;
        $j = 0;
        $noticias = consulta("SELECT n.id_noticia, n.titulo, n.copete, n.link, n.fecha, n.autor FROM noticia as n WHERE n.id_fuente=" . $row['id_fuente'] . " ORDER BY  n.id_noticia DESC LIMIT  3", $dbname="dbname = FeedUncoma");
        while ($row2 = pg_fetch_array($noticias)) {
//            print_r($row2[1]."\n");
            $json[$i][$j] = $row2;
            //print_r($json[$i]);
            $j++;
        }
        pg_free_result($noticias);
        $i++;
    }
    //print_r(json_encode($json));
    pg_free_result($fuentes);
    return json_encode($json);
 
}

//La qeu funciona hasta la fecha 20 de agosto del 2016
function generarJson() {
    
    $fuentes=consulta("SELECT id_fuente, nombre, url, pagina FROM fuente;", $dbname="dbname = FeedUncoma");
    $arr =array('fuentes'=> array());
    $i=0;
    while($row=  pg_fetch_row($fuentes)){
        $arr['fuentes'][$i]=array('nombre' => $row[1], 'pagina'=>$row[3], 'noticias'=>array()) ;
        $noticias=consulta("SELECT n.id_noticia, n.titulo, n.copete, n.link, n.fecha, n.autor FROM noticia as n WHERE n.id_fuente=".$row[0]." ORDER BY  n.fecha DESC LIMIT  3", $dbname="dbname = FeedUncoma");
        $j=0;
        while($row2 = pg_fetch_row($noticias)){
            $arr['fuentes'][$i]['noticias'][$j]=array('titulo'=>$row2[1], 'copete'=>html_entity_decode($row2[2]), 'url'=>$row2[3], 'fecha'=>$row2[4], 'autor'=>$row2[5]);
            $j++;
        }
        pg_free_result($noticias);
        $i++;
    }
//    $arr = array('fuentes' => array(array("nombre" => "prensa", "url" => 2, "noticias" => array(
//             array("titulo" => "titulo1", "copete" => "cop", "url" => "das"),array("titulo" => "titulo1", "copete" => "cop", "url" => "das"))),
//        array("nombre" => "fiuncoma", "url" => 2, "noticias" => array(
//            array("titulo" => "titulo1", "copete" => "cop", "url" => "das"), array("titulo" => "titulo1", "copete" => "cop", "url" => "das")))));

    echo json_encode($arr);
//   
}
//function generarJson() {
//    
//    $fuentes=consulta("SELECT id_fuente, nombre, url, pagina FROM fuente;", $dbname="dbname = FeedUncoma");
//    $arr =array('fuentes'=> array());
//    $i=0;
//    while($row=  pg_fetch_row($fuentes)){
//        $arr['fuentes'][$i]=array('nombre' => $row[1], 'pagina'=>$row[3], 'noticias'=>array()) ;
//        $noticias=consulta("SELECT n.id_noticia, n.id_fuente, n.titulo, n.copete, n.link, n.fecha FROM noticia as n WHERE n.id_fuente=".$row[0]." ORDER BY  n.fecha DESC LIMIT  3", $dbname="dbname = FeedUncoma");
//        $j=0;
//        while($row2 = pg_fetch_row($noticias)){
//            $arr['fuentes'][$i]['noticias'][$j]=array('titulo'=>$row2[1], 'copete'=>html_entity_decode($row2[2]), 'url'=>$row2[3], 'fecha'=>$row2[4]);
//            $j++;
//        }
//        pg_free_result($noticias);
//        $i++;
//    }
////    $arr = array('fuentes' => array(array("nombre" => "prensa", "url" => 2, "noticias" => array(
////             array("titulo" => "titulo1", "copete" => "cop", "url" => "das"),array("titulo" => "titulo1", "copete" => "cop", "url" => "das"))),
////        array("nombre" => "fiuncoma", "url" => 2, "noticias" => array(
////            array("titulo" => "titulo1", "copete" => "cop", "url" => "das"), array("titulo" => "titulo1", "copete" => "cop", "url" => "das")))));
//
//    echo json_encode($arr);
////   
//}