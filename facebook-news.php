<?php
header('Content-Type: application/json; charset=utf-8');

// ***********************************
// WSTAW SWÓJ PAGE ACCESS TOKEN TUTAJ
$pageId      = 'CappellaMarialis'; 
$accessToken = 'EAAVl346pMRgBPZBUd7SZCkLZBNz5xJE1JRE330iyBeTXxguHjweJBZBqdxa0VYfhVTRneEUbItUI9T6ZAfmKZASmn7T48ZBtZAabNI71BHE6VNr2M4FZB7yDyaPWiEeRMb7e7VInp78ehhsR9CKLOqc36HJQpXAVfelxt0S2rlviTihEs744tBx3l68kZArXi7pZCDZCgh97iJ8RweZAQ21ZCdFZA3ZBOzrgoFDmZA3cNGyzP1crRqEbzpRXP3JtD2PQn0ZCUmBeyZAJr8hFaYIXHwfoIMztNmdgZBDd';
// ***********************************

$limit = 5;

$url = "https://graph.facebook.com/v21.0/$pageId/posts" .
       "?fields=message,created_time,full_picture,permalink_url" .
       "&limit=$limit&access_token=$accessToken";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$posts = [];

if (isset($data['data'])) {
    foreach ($data['data'] as $post) {
        if (empty($post['message'])) continue;

        $body = $post['message'];
        if (mb_strlen($body) > 300) {
            $body = mb_substr($body, 0, 297) . '...';
        }

        $posts[] = [
            'title' => explode("\n", $post['message'])[0],
            'body'  => $body,
            'date'  => $post['created_time'] ?? '',
            'image' => $post['full_picture'] ?? '',
            'link'  => $post['permalink_url'] ?? '',
        ];
    }
}

echo json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
