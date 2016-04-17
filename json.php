<?php
include'rss_bd_server.php';
cargarFeed();
//mostrarFeed();
header('Content-Type: application/json');
echo generarJson();
        
