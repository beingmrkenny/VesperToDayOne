<?php

/*

Vesper directory structure:

Active Notes/
	Pictures/
		PhotoFilename.jpg
	Note1.txt
	Note2.txt

Archived Notes/
	Same as above

===== Exported Vesper notes follow this format: ====

Note text note text note text note text note text note text note text
note text note text note text note text note text note text
note text note text

Picture: PhotoFilename.jpg

Tags: Tag1, Tag2, Tag3

Created: YYYY-mm-ddThh:mm:ssZ
Modified: YYYY-mm-ddThh:mm:ssZ

====================================================

*/

$allPosts = array();

$vesperDirectory = '/path/to/vesper/directory';

$notesDirectory = "$vesperDirectory/Active Notes";
$photosDirectory = "$notesDirectory/Pictures";
$outputDirectory = "$vesperDirectory/Output";

function dateFormat($timeStamp) {
    // Assume that the date is stored in GMT
    return gmdate('Y-m-d\TH:i:s\Z', $timeStamp);
}

$files = scandir($notesDirectory);

foreach ($files as $file) {

    if ($file == '.' || $file == '..' || is_dir($file)) {
        continue;
    }

    $note = file_get_contents("$notesDirectory/$file");

    $regex = '/
        (?:Picture: (.*)\n\n)?
        Tags: (.*)\n\n
        Created: (.*)\n
        .*
    /x';

    preg_match($regex, $note, $matches);

    $postText = trim(str_replace($matches[0], '', $note));

    $postDate = $matches[3];

	$postTags = explode(',', $matches[2]);
    $postTags = array_unique($postTags);
    $postTags = array_filter($postTags, function ($value) { return (strlen(trim($value)) > 0); });

	$postPhoto = trim($matches[1]);

    $post = array();

    if (is_array($postTags) && count($postTags) > 0) {
        $post['tags'] = array_map(function ($value) { return trim($value); }, $postTags);
    } else {
        $post['tags'] = array();
    }

    $post['text'] = trim($postText);

    if ($postPhoto) {

        $photoFilepath = "$photosDirectory/$postPhoto";

        $post['tags'][] = 'VesperPhoto';
        $post['text'] .= "\n\nPhoto was: $postPhoto";

        $imageSize = getimagesize($photoFilepath);
        $width = $imageSize[0];
        $height = $imageSize[1];
        $md5Hash = md5_file($photoFilepath);
        $identifier = strtoupper(md5($photoFilepath . time()));

        copy($photoFilepath, "$outputDirectory/photos/$md5Hash.jpeg");

        $photos = array(
            "fnumber"      => "(null)",
            "orderInEntry" => 0,
            "width"        => $width,
            "type"         => "jpeg",
            "identifier"   => $identifier,
            "height"       => $height,
            "md5"          => $md5Hash,
            "focalLength"  => "(null)"
            "date"         => dateFormat(filemtime($photoFilepath))
        );

        $post['photos'] = array($photos);
        $post['text'] = trim("![](dayone-moment://$identifier)\n\n" . $post['text']);
    }

    $post['creationDate'] = dateFormat(strtotime($postDate));
    $post['starred'] = false;
    $post['timeZone'] = 'Europe/London';
    $post['uuid'] = strtoupper(md5($post['text'] . time()));

    $allPosts[] = $post;

}

$jsonOutput = array(
    "metadata" => array("version" => "1.0"),
    "entries" => $allPosts
);

$allPostsJson = json_encode($jsonOutput);

file_put_contents("$outputDirectory/Journal.json", $allPostsJson); // copy json to output folder

header('Content-Type: application/json');
echo $allPostsJson; // output json to screen
