<?php

namespace App\Services\Scraper;

class test
{
    public function test()
    {
        $client = new \GuzzleHttp\Client();
        $url = 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/1-otaqli';

        $html = $client->get($url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]
        ])->getBody()->getContents();
        $parser = new BinaHtmlListingParser();
        $listings = $parser->parseHtmlPage($html, 'rent', 10);
        dd($listings);
    }
}
