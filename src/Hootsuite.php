<?php
/**
 *@copyright	PubliQare BV All Rights Reserved.
 *@author	    Ricardo ricardo.parada@publiqare.com
 */

namespace Sqare\Hoot;
use GuzzleHttp\Exception\GuzzleException;

require 'vendor/autoload.php';
/*
     * possible actions:
     *  {
     *      send a post with text;
     *      send a post with text and an image == $mimeTypeImage;
     *      send a post with text and a video  == $mimeTypeVideo;
     *  }
     *
     * within this three options, we can also choose which social media to post to
     *
     * media file has to be read and the correct parameters must be applied automatically
     * the file mime type is being read and implemented, which also changes the parameters in the body request
     *
     * we can also use an array of social media, but some social media have media format restrictions
     * if twitter is in the array, all media has to be read and resized if needed, same goes to linkedin
     * the problem is the previous not formatted file as already been uploaded to S3 bucket
     * so the solution i implemented was to redo the post/put/getMediaUploadStatus cycle with the formatted video for
     * each social media with their respectively media parameters
     *
     *
     * all possible functions:
     *  {
     *      getAccessToken()
     *      me()
     *      getSocialMediaProfiles()
     *      post()
     *      postMediaRequestGetUrl()
     *      putMediaRequest()
     *      getMediaUploadStatus()
     *      scheduleMessageWithUpload()
     *  }
     *
     * converter currently using:
     * https://github.com/PHP-FFMpeg/PHP-FFMpeg
     */

class Hootsuite
{
    protected $base_uri;
    protected $hootsuiteClientId;
    protected $hootsuiteClientSecret;
    protected $hootsuiteClientMemberId;
    private $config;

    /**
     * @var HootsuiteClient
     */
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @param mixed $config
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = $config;
        $this->base_uri = $this->config['base_uri'];
        $this->hootsuiteClientId = $this->config['hootsuiteClientId'];
        $this->hootsuiteClientSecret = $this->config['hootsuiteClientSecret'];
        $this->hootsuiteClientMemberId = $this->config['hootsuiteClientMemberId'];

        return $this;
    }

    /**
     *
     */
    public function getUserId()
    {
        $this->client->getAccessToken();
        $this->client->me();
    }

    /**
     *
     */
    public function getSocialMediaProfiles()
    {
        $this->client->getAccessToken();
        $this->client->getSocialMediaProfiles();

    }

    /**
     * @param $text
     * @param $scheduleSendTime
     * @param $socialMediaProfiles
     */
    public function postText($text, $scheduleSendTime, $socialMediaProfiles)
    {
        $this->client->getAccessToken();
        $this->client->post($text, $scheduleSendTime, $socialMediaProfiles);
    }

    /**
     * @param $text
     * @param $title
     * @param $scheduleSendTime
     * @param $socialMediaProfiles
     * @param $filePath
     * @param $fileToUpload
     */
    public function postWithMedia($text, $title, $scheduleSendTime, $socialMediaProfiles, $filePath, $fileToUpload)
    {
        $this->client->getAccessToken();
        $this->client->postMediaRequestGetUrl($filePath);
        $this->client->putMediaRequest($fileToUpload);
        $this->client->getMediaUploadStatus();
        $this->client->scheduleMessageWithUpload($text, $title, $scheduleSendTime, $socialMediaProfiles);
    }
}