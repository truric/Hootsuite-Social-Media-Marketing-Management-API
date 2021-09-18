<?php

use GuzzleHttp\Client;
use Sqare\Hoot\Hootsuite;
use Sqare\Hoot\HootsuiteClient;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once '../vendor/autoload.php';

$httpClient =  new Client([
    'base_uri'  => 'https://platform.hootsuite.com'
]);

$logger = new Logger('channel-name');
$logger->pushHandler(new StreamHandler(__DIR__ . '/app.log', Logger::DEBUG));
$logger->debug("Starting");


$client = new HootsuiteClient($httpClient, $logger, Locator::class);
$client->setBaseUri('https://platform.hootsuite.com')
    ->setHootsuiteClientId('807b70fd-887b-4fcd-856c-2f49d1ce2c74')
    ->setHootsuiteClientSecret('UDr_1v4gmpgk')
    ->setHootsuiteClientMemberId('23865402')
    ->setVideoConversionBinPath('C:/Users/RP/Desktop/Hootsuite with FFMpeg/ffmpeg-2021-07-21-git-f614390ecc-full_build/bin/ffmpeg.exe'); //hardcoded for testing purposes
//    ->setVideoConversionBinPath('/usr/local/bin'); //which ffmpeg (MAC OS path)

$hoot = new Hootsuite($client);

echo $client->getAccessToken(), "\n";
echo $client->me(), "\n";
echo $client->getSocialMediaProfiles(), "\n";
echo $client->post("Sent with phpstorm", [$client->socialMediaProfiles['FACEBOOKPAGE'], $client->socialMediaProfiles['TWITTER'], $client->socialMediaProfiles['LINKEDINCOMPANY']], "2021-08-2T11:00:00Z"), "\n";
echo $client->postMediaRequestGetUrl('C:\Users\RP\Downloads\sample.mp4'), "\n";

echo $client->putMediaRequest('C:\Users\RP\Downloads\sample.mp4'), "\n";

while($client->getMediaUploadStatus() != "READY" ){
    echo "Waiting for upload to complete";
    sleep(2);
}

echo $client->scheduleMessageWithUpload("Last Last send with phpstorm", "Add a title here", [$client->socialMediaProfiles['FACEBOOKPAGE'], $client->socialMediaProfiles['LINKEDINCOMPANY']], "2021-08-2T11:00:00Z"), "\n";
