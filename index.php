<?php
/**
 * Created by PhpStorm.
 * User: Hiệp Nguyễn
 * Date: 07/04/2022
 * Time: 16:59
 */

require_once __DIR__ . "/src/Parser.php";

$events = extractEvents('https://dantri.com.vn/kinh-doanh/huy-dong-von-cho-vinfast-nuoc-co-moi-cua-ty-phu-vuong-tren-dat-my-20220408081321465.htm');
var_dump($events);