<?php
/**
 * Created by PhpStorm.
 * User: Hiệp Nguyễn
 * Date: 07/04/2022
 * Time: 22:05
 */


function parseHtml(string $contents): array
{
    preg_match("/<main(.*?)>(.*?)<\/main>/u", $contents, $matches);
    $main     = $matches[0];
    $articles = getTags($main, "article");
    $events   = [];
    foreach ($articles as $article) {
        $paragraphs = getTags($article, "p");
        $events[]   = parseEvent($paragraphs);
    }
    return array_merge(...$events);
}

function getSentences(string $contents): array
{
    return preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $contents);
}

function parseEvent(array $paragraphs): array
{
    $events = [];
    foreach ($paragraphs as $paragraph) {
        $text = strip_tags($paragraph);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace(["&nbsp;", "&amp;"], '', $text);

        $sentences = getSentences($text);
        foreach ($sentences as $sentence) {
            if ($dates = getDates($sentence)) {
                foreach ($dates as $date) {
                    $events[] = [
                        "date"     => $date,
                        "event"    => $sentence,
                        "found_on" => $text
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
    $date_regexes = [
        // m/Y
        "/[0-9]{1,2}\/[0-9]{4}/u",
        // d/m
        "/[0-9]{1,2}\/[0-9]{1,2}/u",
        // d/m/Y
        "/[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}/u",
        // m-Y
        "/[0-9]{1,2}\-[0-9]{4}/u",
        // d-m
        "/[0-9]{1,2}\-[0-9]{1,2}/u",
        // d-m-Y
        "/[0-9]{1,2}\-[0-9]{1,2}\-[0-9]{4}/u",
        // m.Y
        "/[0-9]{1,2}\.[0-9]{4}/u",
        // d.m
        "/[0-9]{1,2}\.[0-9]{1,2}/u",
        // d.m.Y
        "/[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4}/u",
    ];
    $dates        = [];
    foreach ($date_regexes as $date_regex) {
        if (preg_match_all($date_regex, $contents, $matches)) {
            $dates[] = $matches[0];
        }
    }
    $dates   = array_unique(array_merge(...$dates));
    $results = [];
    foreach ($dates as $date) {
        if (isValidDate($date)) {
            $results[] = $date;
        }
    }
    return $results;
}

function isValidDate(string $date): bool
{
    $data = [];
    if (str_contains($date, "/")) {
        $data = explode("/", $date);
    }
    if (str_contains($date, "-")) {
        $data = explode("-", $date);
    }
    if (str_contains($date, ".")) {
        $data = explode(".", $date);
    }
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

