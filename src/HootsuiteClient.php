<?php
/**
*@copyright	PubliQare BV All Rights Reserved.
*@author	Ricardo ricardo.parada@publiqare.com
*/

namespace Sqare\Hoot;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

class HootsuiteClient
{
    /**
     * @param $httpClient
     * @param LoggerInterface $logger
     * @param Locator|null $locator
     */
    public function __construct($httpClient, LoggerInterface $logger, Locator $locator = null)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->locator = $locator;
    }

    /**
     * @var int
     */
    const MAXTIME = 15*60;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var Locator
     */
    private $locator;

    /**
     * @var string
     */
    private $accessToken;
    /**
     * @var false|string
     */

    /**
     * @var string|null
     */
    protected $fileId = null;

    /**
     * @var string
     */
    protected $scheduleSendTime;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var string
     */
    private $mimeTypeImage = 'image/jpg';

    /**
     * @var string
     */
    private $mimeTypeVideo = 'video/mp4';

    /**
     * @var string
     */
    private $fileMimeType;

    /**
     * @var integer
     */
    private $resolution;

    /**
     * @var string
     */
    private $convertedFilePath;

    /**
     * @var array
     */
    public $socialMediaProfiles;

    /**
     * @var object
     */
    private $response;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var string
     */
    private $hootsuiteClientSecret;

    /**
     * @var string
     */
    private $hootsuiteClientId;

    /**
     * @var integer
     */
    private $hootsuiteClientMemberId;

    /**
     * @var string
     */
    private $base_uri;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $videoConversionBinPath;

    /**
     * @param string $hootsuiteClientId
     * @return $this
     */
    public function setHootsuiteClientId(string $hootsuiteClientId)
    {
        $this->hootsuiteClientId = $hootsuiteClientId;
        return $this;
    }

    /**
     * @param string $hootsuiteClientSecret
     * @return $this
     */
    public function setHootsuiteClientSecret(string $hootsuiteClientSecret)
    {
        $this->hootsuiteClientSecret = $hootsuiteClientSecret;
        return $this;
    }

    /**
     * @param string $hootsuiteClientMemberId
     */
    public function setHootsuiteClientMemberId(string $hootsuiteClientMemberId)
    {
        $this->hootsuiteClientMemberId = $hootsuiteClientMemberId;
    }

    /**
     * @param string $base_uri
     * @return $this
     */
    public function setBaseUri(string $base_uri)
    {
        $this->base_uri = $base_uri;
        return $this;
    }

    /**
     * @return string
     */
    private function getFileId():string
    {
        return $this->fileId;
    }

    /**
     * @param $mimeTypeVideo
     */
    public function setMimeTypeVideo($mimeTypeVideo)
    {
        $this->mimeTypeVideo = $mimeTypeVideo;
    }

    /**
     * @param string $mimeTypeImage
     */
    public function setMimeTypeImage($mimeTypeImage)
    {
        $this->mimeTypeImage = $mimeTypeImage;
    }

    /**
     * @param string $videoConversionBinPath
     * @return $this
     */
    public function setVideoConversionBinPath(string $videoConversionBinPath)
    {
        $this->videoConversionBinPath = $videoConversionBinPath;
        return $this;
    }

    /**
     * @return string
     * @throws GuzzleException
     */
    public function getAccessToken():string
    {
        try {

            if($this->accessToken){
                $this->logger->debug("access token is still active");
                return true;
            }

            $response = $this->httpClient->post('/auth/oauth/v2/token', [
                'form_params' => [
                    'grant_type' => 'member_app',
                    'member_id' => $this->hootsuiteClientMemberId,
                    'scope' => 'offline',
                ],
                'headers' => [
                    'Authorization' => 'Basic ' .
                        base64_encode($this->hootsuiteClientId . ':' . $this->hootsuiteClientSecret)
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $tokenData = $response->getBody();

        $decode = json_decode($tokenData, true);
        $accessToken = $decode['access_token'];

        return $this->accessToken = $accessToken;
    }

    /**
     * @return string
     * @throws GuzzleException
     */
    public function me():string
    {
        try {
            $response = $this->httpClient->get('/v1/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $tokenData = $response->getBody();
        return $tokenData->getContents();
    }

    /**
     * @throws GuzzleException
     */
    public function getSocialMediaProfiles():void
    {
        try {
            $response = $this->httpClient->get('/v1/socialProfiles', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $tokenData = $response->getBody();
        $tokenData = $tokenData->getContents();

        $decode = json_decode($tokenData, true);
/*      Counting the number of social profile associated with logged user and listing all the available social profile
        ids, here are the list of possible social profile names: TWITTER, YOUTUBECHANNEL, FACEBOOKPAGE, LINKEDIN,
        FACEBOOK, PINTEREST, INSTAGRAMBUSINESS, LINKEDINCOMPANY    */
        for ($i = 0; $i < count($decode['data']); $i++) {
            $this->socialMediaProfiles[(string)$decode['data'][$i]['type']] = (int)($decode['data'][$i]['id']);
            $this->logger->debug($this->socialMediaProfiles[(string)$decode['data'][$i]['type']] . PHP_EOL);
        }
    }

    /**
     * @param $text
     * @param $socialMediaProfiles
     * @param null $scheduleSendTime
     * @return string
     * @throws GuzzleException
     */
    /*  The time the message is scheduled to be sent is UTC time, ISO-8601 format. Missing or different timezones will
        not be accepted, to ensure there is no ambiguity about scheduled time. Dates must end with 'Z' to be accepted.
        If there is no scheduleSendTime parameter, the message will be sent as soon as it's processed.
        $scheduleSendTime example: "2021-07-15T12:15:00Z"                                                           */

    public function post($text, $socialMediaProfiles, $scheduleSendTime = null):string
    {
        $oldTime = ini_get('max_execution_time');
        set_time_limit(self::MAXTIME);

        $form_params = [
            'text' => $this->text = $text,
            'socialProfileIds' => $socialMediaProfiles,
            'scheduledSendTime' => $this->scheduleSendTime = $scheduleSendTime,
            'emailNotification' => true
        ];

    //casting as float, if the user inserts latitude as 'A' for example, it converts into zero so the code won't break
        if ($this->locator)
        {
            $location = $this->locator::getLocation();
            $form_params['location'] = [
                (float) $location[0],
                (float) $location[1]
            ];
        }

        try {
            $response = $this->httpClient->post('/v1/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $form_params
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return $e->getMessage();
        }
        $tokenData = $response->getBody();
        set_time_limit($oldTime);

        return $tokenData->getContents();
    }

    /**
     * @param $filePath
     * @param null $fileId
     * @return string
     * @throws GuzzleException
     */
    public function postMediaRequestGetUrl($filePath, &$fileId = null): string
    {
        $size = filesize($filePath) ?: 0;
        pathinfo($filePath, PATHINFO_EXTENSION) == 'mp4' || filetype($filePath) == 'mov'
            ? $this->fileMimeType = $this->mimeTypeVideo : $this->fileMimeType = $this->mimeTypeImage;
            $oldTime = ini_get('max_execution_time');
            set_time_limit(self::MAXTIME);

        try {
            $response = $this->httpClient->post('/v1/media', [
                RequestOptions::JSON => [
                    'sizeBytes' => $size,
                    'mimeType' => $this->fileMimeType
                ],
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-type' => 'Application/json',
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $tokenData = $response->getBody();

        $decode = json_decode($tokenData, true);
        $putUrl = $decode['data']['uploadUrl'];

        $this->fileId = $fileId = $decode['data']['id'];
        $this->filePath = $filePath;

        $this->filePath = $filePath;
        set_time_limit($oldTime);

        return $this->putUrl = $putUrl;
    }

    /**
     * @param $fileToUpload
     * @return string
     * @throws GuzzleException
     */
    public function putMediaRequest($fileToUpload):string
    {
        if (!$this->putUrl) {
            return "ERROR no upload url";
        }
        $oldTime = ini_get('max_execution_time');
        set_time_limit(self::MAXTIME);

        if (!$this->convertedFilePath) {
            $fileToUpload = $this->filePath;
        } else {
            $fileToUpload = $this->convertedFilePath;
        }
        $fileSize = filesize($fileToUpload) ?: 0;
        try {

            $this->logger->debug(printf("FileSize to upload: %s", $fileSize));

            $requestParams = [
                RequestOptions::HEADERS => [
                    'Content-Type' => $this->fileMimeType,
                    'Content-Length' => $fileSize

                ],
                RequestOptions::BODY => file_get_contents($fileToUpload)
            ];

            $response = $this->httpClient->request(
                'PUT',
                $this->putUrl,
                $requestParams
            );

            if (!$response->getStatusCode() == 200) {
                throw new Exception(sprintf("API Auth error: %s", $response->getReasonPhrase()));
            }

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        set_time_limit($oldTime);
        return true;
    }

    /**
     * @return string
     * @throws GuzzleException
     * @throws Exception
     */
    function getMediaUploadStatus():string
    {
        $oldTime = ini_get('max_execution_time');
        set_time_limit(self::MAXTIME);

        $this->logger->debug('Getting media upload status.');

        $uri = sprintf("/v1/media/%s", $this->fileId);

        $response = $this->httpClient->request('GET', $uri, [
            'headers' => [
                'Authorization' => sprintf("Bearer %s", $this->accessToken)

            ]
        ]);

        if (!$response->getStatusCode() == 200) {
            throw new Exception(sprintf("API Auth error: %s", $response->getReasonPhrase()));
        }


        if (!$result = json_decode($response->getBody()->getContents(), true)) {
            throw new Exception("API response couldn't be decoded (JSON).");
        }
        set_time_limit($oldTime);
        $this->logger->debug($result['data']['state']);
        return $result['data']['state'];
    }


    /**
     * @param $text
     * @param $title
     * @param $socialMediaProfiles
     * @param null $scheduleSendTime
     * @return void
     */
        /*  If your media type is a video, you must schedule it at least 15 minutes into the future.
            Alternatively, you can send a message without a fixed send time and it will automatically be assigned the
            soonest possible send time.
            Note: Specifying a custom thumbnail is not yet supported. A thumbnail will be auto generated for the
            uploaded media. */
    public function scheduleMessageWithUpload($text, $title, $socialMediaProfiles, $scheduleSendTime = null): void
    {
        $oldTime = ini_get('max_execution_time');

        foreach ($socialMediaProfiles as $socialMediaProfile) {
            set_time_limit(self::MAXTIME);
            $requestParamsFileTypeVideo = [
                RequestOptions::JSON =>
                    [
                        "text" => $this->text = $text,
                        "scheduledSendTime" => $this->scheduleSendTime = $scheduleSendTime,
                        'socialProfileIds' => [$socialMediaProfile],

                        "media" => [
                            [
                                "id" => $this->fileId,
                                "videoOptions" => [
                                    "facebook" => [
                                        "title" => $this->title = $title,
                                        "category" => "ENTERTAINMENT"
                                        /*  This is a facebook object, can't input just any string
                                            "BEAUTY_FASHION", "BUSINESS", "CARS_TRUCKS", "COMEDY", "CUTE_ANIMALS", "ENTERTAINMENT",
                                            "FAMILY", "FOOD_HEALTH", "HOME", "LIFESTYLE", "MUSIC", "NEWS", "POLITICS", "SCIENCE",
                                            "SPORTS", "TECHNOLOGY", "VIDEO_GAMING", "OTHER"                                      */
                                    ]
                                ]
                            ]
                        ]
                    ],
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],

            ];

            $requestParamsFileTypeImage = [
                RequestOptions::JSON =>
                    [
                        "text" => $this->text = $text,
                        "scheduledSendTime" => $this->scheduleSendTime = $scheduleSendTime,
                        'socialProfileIds' => [$socialMediaProfile],

                        "media" => [
                            [
                                "id" => $this->fileId,
                            ]
                        ]
                    ],
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],

            ];

            $this->fileMimeType != $this->mimeTypeImage ? $requestParams = $requestParamsFileTypeVideo
                : $requestParams = $requestParamsFileTypeImage;

            //here we can add any working social media in the near future with their media parameters
            switch ($socialMediaProfile) {

                case $this->socialMediaProfiles['INSTAGRAMBUSINESS']:
                case $this->socialMediaProfiles['FACEBOOKPAGE'];
                    $this->tryCatchResponse($requestParams);
                    $this->logger->debug($this->response);
                    break;

                case $this->socialMediaProfiles['TWITTER'];
                    $this->resolution = '1280x720';
                    //convert file for each social media with their video parameters
                    $this->convertVideo();
                    //here we go again, need a new post URL, a new fileId, putUrl, etc with the new formatted video
                    $this->postMediaRequestGetUrl($this->convertedFilePath);
                    $this->putMediaRequest($this->convertedFilePath);
                    $now = time();
                    while ($this->getMediaUploadStatus() != "READY" && (time() > $now + 60)) {
                        echo "Waiting for upload to complete";
                        sleep(2);
                    }
                    $this->tryCatchResponse($requestParams);
                    $this->logger->debug($this->response);
                    break;

                case $this->socialMediaProfiles['LINKEDINCOMPANY'];
                    $this->resolution = '400x960';
                    $this->convertVideo();
                    $this->postMediaRequestGetUrl($this->convertedFilePath);
                    $this->putMediaRequest($this->convertedFilePath);
                    $now = time();
                    while ($this->getMediaUploadStatus() != "READY" && (time() > $now + 60)) {
                        echo "Waiting for upload to complete";
                        sleep(2);
                    }
                    $this->tryCatchResponse($requestParams);
                    $this->logger->debug($this->response);
                    break;
            }
        }
        if(file_exists($this->convertedFilePath))
        {
            unlink($this->convertedFilePath);
        }
        set_time_limit($oldTime);
    }

    /*
     * Using FFMpeg we can format the video directly with the desired resolution
     * As this is being ran in command line, FFMpeg needs to be installed in CDH
     */
    private function convertVideo():void
    {
        $convertedFileName = basename($this->filePath);
        $command = trim($this->videoConversionBinPath) . '-i ' . $this->filePath . ' -s ' . $this->resolution . ' ' . $convertedFileName;
//        $command = 'ffmpeg ' . '-i ' . $this->filePath . ' -s ' . $this->resolution . ' ' . $convertedFileName;
        $this->convertedFilePath = tempnam(sys_get_temp_dir(), $convertedFileName);
//        $this->convertedFilePath = 'C:/Users/RP/Desktop/Hootsuite with FFMpeg/' . $convertedFileName;
        system($command);

        $this->logger->debug("File has been converted" . PHP_EOL);
    }

    /*
     * Trying to avoid code repetition
     * This will be used for each social media
     */
    /**
     * @param $requestParams
     * @return object|null
     * @throws GuzzleException
     * @throws Exception
     */
    private function tryCatchResponse($requestParams): ?object
    {
        try {
            $response = $this->httpClient->request('POST', '/v1/messages', $requestParams);
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $getErrorMessageAsString = json_encode($e->getMessage(), true);
            $errorMessageParts = explode(':', $getErrorMessageAsString);
            if (substr($errorMessageParts[5], 0, 5) == 40009) {
                throw new Exception('The width of the video is too big. In order to post a video on Twitter, max width = 1280' . PHP_EOL);
            }
        }
        if ($this->response !=null)
        {
            return $this->response = $response;
        }else
            return null;
    }
}

