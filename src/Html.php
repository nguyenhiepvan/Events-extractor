<?php
/**
 * Created by PhpStorm.
 * User: Hiệp Nguyễn
 * Date: 07/04/2022
 * Time: 22:05
 */


function parseHtml(string $contents): array
{
    $events          = [];
    $year_created_at = tryToGetYearCreatedAt($contents);
    $mains           = getTags($contents, "main");
    foreach ($mains as $main) {
        $articles = getTags($main, "article");
        [$breadcrumbs, $keywords] = parseKeywords($main);
        foreach ($articles as $article) {
            $paragraphs = getTags($article, "p");
            $events[]   = parseEvent($paragraphs, $breadcrumbs, $keywords, $year_created_at);
        }
    }
    return array_merge(...$events);
}

function tryToGetYearCreatedAt(string $contents): int
{
    $day       = "(?<day>\b(0?[1-9]|[12][0-9]|3[01])\b)";
    $month     = "(?<month>\b(0?[1-9]|[1][0-2])\b)";
    $year      = "(?<year>[12][0-9]{3})"; //Years from 1000 to 2999
    $hour      = "(?<hour>\b(0?[0-9]|[1][0-9]|[2][0-3])\b)";
    $minutes   = "(?<minutes>\b(0?[0-9]|[1-5][0-9]|[6][0])\b)";
    $seconds   = "(?<seconds>\b(0?[0-9]|[1-5][0-9]|[6][0])\b)";
    $separator = "(\:|\.|\,|\)|\-|\_|\/|\\|\}|\])";

    $date_regexes = [
        "/{$hour}{$separator}{$minutes}{$separator}?{$seconds}?\s?{$separator}?\s?{$day}{$separator}{$month}{$separator}{$year}/u",
        "/{$day}{$separator}{$month}{$separator}{$year}\s?{$separator}?\s?{$hour}{$separator}{$minutes}{$separator}?{$seconds}?/u",
    ];
    foreach ($date_regexes as $date_regex) {
        if (preg_match($date_regex, $contents, $matches)) {
            return $matches["year"];
        }
    }

    return tryToGetBestYear($contents);
}

function tryToGetBestYear(string $contents): int
{
    $year = "(?<year>[12][0-9]{3})"; //Years from 1000 to 2999
    if (preg_match_all("/$year/u", $contents, $matches)) {
        $values = array_count_values($matches["year"]);
        arsort($values);
        return array_key_first($values);
    }
    return date("y");
}

function parseKeywords(string $main): array
{
    $ignore_breadcrumbs = [
        "home",
        "trang chủ",
    ];
    $ignore_keywords    = [
        "tag",
        "keyword",
        "từ khóa",
    ];
    $breadcrumbs        = [];
    $keywords           = [];
    $uls                = getTags($main, "ul");
    foreach ($uls as $ul) {
        $lis = getTags($ul, "li");
        if (str_contains($ul, "breadcrumb")) {
            foreach ($lis as $li) {
                $breadcrumb    = strip_tags($li);
                $breadcrumbs[] = trim($breadcrumb);
            }
        }
        if (str_contains($ul, "tag")) {
            foreach ($lis as $li) {
                $keyword    = strip_tags($li);
                $keywords[] = trim($keyword);
            }
        }
    }
    $breadcrumbs = array_filter($breadcrumbs, static function ($v, $k) use ($ignore_breadcrumbs) {
        $keyword = mb_strtolower($v, "UTF-8");
        $keyword = stripVN($keyword);
        foreach ($ignore_breadcrumbs as $ignore_keyword) {
            if (str_contains($keyword, stripVN($ignore_keyword))) {
                return false;
            }
        }
        return true;
    }, ARRAY_FILTER_USE_BOTH);

    $keywords = array_filter($keywords, static function ($v, $k) use ($ignore_keywords) {
        $keyword = mb_strtolower($v, "UTF-8");
        $keyword = stripVN($keyword);
        foreach ($ignore_keywords as $ignore_keyword) {
            if (str_contains($keyword, stripVN($ignore_keyword))) {
                return false;
            }
        }
        return true;
    }, ARRAY_FILTER_USE_BOTH);

    return [$breadcrumbs, $keywords];
}

function stripVN(string $str): string
{
    $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u", 'a', $str);
    $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u", 'e', $str);
    $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/u", 'i', $str);
    $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u", 'o', $str);
    $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u", 'u', $str);
    $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/u", 'y', $str);
    $str = preg_replace("/(đ)/u", 'd', $str);

    $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/u", 'A', $str);
    $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/u", 'E', $str);
    $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/u", 'I', $str);
    $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/u", 'O', $str);
    $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/u", 'U', $str);
    $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/u", 'Y', $str);
    return preg_replace("/(Đ)/u", 'D', $str);
}

function getSentences(string $contents): array
{
    return preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $contents);
}

function parseEvent(array $paragraphs, array $breadcrumbs = [], array $keywords = [], int $year_created_at = 0): array
{
    $events = [];
    foreach ($paragraphs as $paragraph) {
        $text = strip_tags($paragraph);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace(["&nbsp;", "&amp;"], '', $text);

        $sentences = getSentences($text);
        foreach ($sentences as $sentence) {
            if ($dates = getDates($sentence)) {
                $dates = standardizeDate($dates, $year_created_at);
                foreach ($dates as $date) {
                    $events[] = [
                        "date"       => $date,
                        "event"      => $sentence,
                        "found_on"   => $text,
                        "breadcrumb" => $breadcrumbs,
                        "keywords"   => $keywords
                    ];
                }
            } else if (shouldMerge($sentence)) {
                $last_key                   = array_key_last($events);
                $events[$last_key]["event"] .= $sentence;
            }
        }
    }
    return $events;
}

function standardizeDate(array $dates, int $year_created_at): array
{
    $results   = [];
    $separator = "(\:|\.|\,|\)|\-|\_|\/|\\|\}|\])";
    foreach ($dates as $date) {
        $data = preg_split("/$separator/i", $date);
        foreach ($data as &$_data) {
            $_data = str_pad($_data, 2, '0', STR_PAD_LEFT);
        }
        if (count($data) === 2 && $data[array_key_last($data)] < 13) {
            $data[] = $year_created_at;
        }
        $results[] = implode("/", $data);
    }
    return $results;
}

function shouldMerge(string $sentence): bool
{
    $words = explode(" ", $sentence);
    foreach ($words as $word) {
        if (str_contains($word, ".,")) {
            return true;
        }
    }
    return false;
}

function getDates(string $contents): array
{
    $day          = "(?<day>\b(0?[1-9]|[12][0-9]|3[01])\b)";
    $month        = "(?<month>\b(0?[1-9]|[1][0-2])\b)";
    $year         = "(?<year>[12][0-9]{3})"; //Years from 1000 to 2999
    $separator    = "(\:|\.|\,|\)|\-|\_|\/|\\|\}|\])";
    $date_regexes = [
        "/{$day}{$separator}{$month}/u",
        "/{$month}{$separator}{$year}/u",
        "/{$day}{$separator}{$month}{$separator}{$year}/u",
    ];
    $dates        = [];
    foreach ($date_regexes as $date_regex) {
        if (preg_match_all($date_regex, $contents, $matches)) {
            $dates[] = $matches[0];
        }
    }
    $dates   = array_unique(array_merge(...$dates));
    $results = [];
    for ($i = 0, $iMax = count($dates); $i < $iMax - 1; $i++) {
        for ($j = $i + 1; $j < $iMax; $j++) {
            if (str_contains($dates[$j], $dates[$i])) {
                unset($dates[$i]);
            }
        }
    }
    foreach ($dates as $date) {
        if (isValidDate($date)) {
            $results[] = $date;
        }
    }

    return $results;
}

function isValidDate(string $date): bool
{
    $separator = "(\:|\.|\,|\)|\-|\_|\/|\\|\}|\])";
    $data      = preg_split("/$separator/i", $date);
    if (count($data) === 2) {
        // d/m
        if (checkdate($data[1], $data[0], date("Y"))) {
            return true;
        }
        // m/Y
        if (checkdate($data[0], 1, $data[1])) {
            return true;
        }
    }
    return (count($data) === 3) && checkdate($data[1], $data[0], $data[2]);
}

function getTags(string $contents, string $tag_name): array
{
    preg_match_all("/(?<elements><$tag_name(.*?)>(.*?)<\/$tag_name>)/u", $contents, $matches);
    $elements = $matches["elements"] ?? [];
    if (count($elements) < 2) {
        return $elements;
    }
    $results = [];
    foreach ($elements as $element) {
        if (count($_elements = getTags($element, $tag_name)) > 0) {
            $results[] = $_elements;
        } else {
            $results[] = $element;
        }
    }
    return array_merge(...$results);
}

function optimizeHtml(string $contents): string
{
    $remove_tags = [
        "style",
        "script",
        "link",
        "img",
        "iframe",
        "noscript",
        "header",
        "figure",
        "footer",
        "nav",
        "head",
    ];

    foreach ($remove_tags as $tag) {
        if ($_contents = preg_replace('#<' . $tag . '(.*?)>(.*?)</' . $tag . '>#is', '', $contents)) {
            $contents = $_contents;
        }
    }

    foreach ($remove_tags as $tag) {
        if ($_contents = preg_replace('#<' . $tag . '(.*?)>#is', '', $contents)) {
            $contents = $_contents;
        }
    }
    return str_replace(PHP_EOL, "", $contents);
}

function getContents(string $url): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}