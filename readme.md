# Hootsuite #

Hootsuite is the most used social media management platform in the world, with over 16 million users.

Our goal it to use its REST API and build our own code around our CDH, that way any client can manage their own social
media albeit a single text message, an image or even a video, with the ability to send the same post so all social
media at once or even schedule them for a later date.

## Installation ##

Guzzle, the recommended way to install Guzzle is with Composer:

    $ composer require guzzlehttp/guzzle:^7.0

Monolog, install the latest version with:

    $ composer require monolog/monolog

FFMpeg binary installed [direct link](https://www.ffmpeg.org/)

## Documentation ##

- [Overview Guide](https://developer.hootsuite.com/docs/api-guides)
- [API Endpoints Guide](https://platform.hootsuite.com/docs/api/index.html#operation/oauth2Token)
- [Download Swagger File](https://platform.hootsuite.com/docs/api/index.html#)

## Functions and usage ##

#### getAccessToken() ####
- For any action, you will always need a working access token
  
__Usage__:

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
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $tokenData = $response->getBody();

        $decode = json_decode($tokenData, true);
        $accessToken = $decode['access_token'];

        return $this->accessToken = $accessToken;
    }

#### me() ####
- Returns logged user info 

__Usage__:
    
    public function me():string
    {
        try {
            $response = $this->httpClient->get('/v1/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $tokenData = $response->getBody();
        return $tokenData->getContents();
    }

#### getSocialMediaProfiles() ####
- Returns all the social media available for the logged user

__Usage__:

    public function getSocialMediaProfiles():void
    {
        try {
            $response = $this->httpClient->get('/v1/socialProfiles', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $tokenData = $response->getBody();
        $tokenData = $tokenData->getContents();

        $decode = json_decode($tokenData, true);

        for ($i = 0; $i < count($decode['data']); $i++) {
            $this->socialMediaProfiles[(string)$decode['data'][$i]['type']] = (int)($decode['data'][$i]['id']);
            $this->logger->debug($this->socialMediaProfiles[(string)$decode['data'][$i]['type']] . PHP_EOL);
        }
    }

#### post() ####
- Sends or schedules a text post without media files

__Usage__:

    public function post($text, $scheduleSendTime, $socialMediaProfiles):string
    {
        $form_params = [
            'text' => $this->text = $text,
            'socialProfileIds' => $socialMediaProfiles,
            'scheduledSendTime' => $this->scheduleSendTime = $scheduleSendTime,
            'location' => [
                'latitude' => 34.56,
                'longitude' => -12.34
            ],
            'emailNotification' => true
        ];
        try {
            $response = $this->httpClient->post('/v1/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $form_params
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $e->getMessage();
        }
        $tokenData = $response->getBody();

        return $tokenData->getContents();
    }

#### postMediaRequestGetUrl() ####
- In order to send a post with a media file, first we need to make a request upload to a S3 bucket
- This function will return an upload URL and a unique file ID

__Usage__:

    public function postMediaRequestGetUrl($filePath, &$fileId = null): string
    {
        $size = filesize($filePath) ?: 0;
        pathinfo($filePath, PATHINFO_EXTENSION) == 'mp4' || filetype($filePath) == 'mov'
            ? $this->fileMimeType = $this->mimeTypeVideo : $this->fileMimeType = $this->mimeTypeImage;

        try {
            $response = $this->httpClient->post('/v1/media', [
                \GuzzleHttp\RequestOptions::JSON => [
                    'sizeBytes' => $size,
                    'mimeType' => $this->fileMimeType
                ],
                \GuzzleHttp\RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-type' => 'Application/json',
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $tokenData = $response->getBody();

        $decode = json_decode($tokenData, true);
        $putUrl = $decode['data']['uploadUrl'];

        $this->fileId = $fileId = $decode['data']['id'];
        $this->filePath = $filePath;

        $this->filePath = $filePath;

        return $this->putUrl = $putUrl;
    }

#### postMediaRequestGetUrl() ####
- With the previous task complete, we will now use that upload URL and file ID
- The file will now be uploaded to a S3 bucket

__Usage__:

    public function putMediaRequest($fileToUpload):string
    {
        if (!$this->putUrl) {
            return "ERROR no upload url";
        }

        if (!$this->convertedFilePath) {
            $fileToUpload = $this->filePath;
        } else {
            $fileToUpload = $this->convertedFilePath;
        }
        $fileSize = filesize($fileToUpload) ?: 0;
        try {

            $this->logger->debug(printf("FileSize to upload: %s", $fileSize));

            $requestParams = [
                \GuzzleHttp\RequestOptions::HEADERS => [
                    'Content-Type' => $this->fileMimeType,
                    'Content-Length' => $fileSize

                ],
                \GuzzleHttp\RequestOptions::BODY => file_get_contents($fileToUpload)
            ];

            $response = $this->httpClient->request(
                'PUT',
                $this->putUrl,
                $requestParams
            );

            if (!$response->getStatusCode() == 200) {
                throw new Exception(sprintf("API Auth error: %s", $response->getReasonPhrase()));
            }

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return true;
    }

#### getMediaUploadStatus() ####
- This is an auxiliary function that helps us know it the file was successfully uploaded

__Usage__:

    function getMediaUploadStatus():string
    {

        printf("<p>Getting media upload status.</p>");

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
            throw new Exception(sprintf("API response couldn't be decoded (JSON)."));
        }

        $this->logger->debug($result['data']['state']);
        return $result['data']['state'];
    }

#### scheduleMessageWithUpload() ####
- We can now send a post with the uploaded file
- **NOTE:** <u> different social media have different file size and format requirements, this function with the help of
FFmpeg will convert each file for each social media the user is attempting to send the post to </u>
- If your media type is a video, you must schedule it at least 15 minutes into the future.
Alternatively, you can send a message without a fixed send time and it will automatically be assigned the soonest
possible send time.

__Usage__:

    public function scheduleMessageWithUpload($text, $scheduleSendTime, $title, $socialMediaProfiles):string
    {

        foreach ($socialMediaProfiles as $socialMediaProfile) {
            $requestParamsFileTypeVideo = [
                \GuzzleHttp\RequestOptions::JSON =>
                    [
                        "text" => $this->text = $text,
                        "scheduledSendTime" => $this->scheduleSendTime = $scheduleSendTime,
                        'socialProfileIds' => [$socialMediaProfile],

                        "media" => [
                            [
                                "id" => $this->getFileId(),
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
                \GuzzleHttp\RequestOptions::JSON =>
                    [
                        "text" => $this->text = $text,
                        "scheduledSendTime" => $this->scheduleSendTime = $scheduleSendTime,
                        'socialProfileIds' => [$socialMediaProfile],

                        "media" => [
                            [
                                "id" => $this->getFileId(),
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

                case $this->socialMediaProfiles['FACEBOOKPAGE'];
                    $this->tryCatchResponse($requestParams);
                    echo $this->response->getBody();
                    break;

                case $this->socialMediaProfiles['TWITTER'];
                    $this->resolution = '1280x720';
                    //convert file for each social media with their video parameters
                    $this->convertVideo();
                    //here we go again, need a new post URL, a new fileId, putUrl, etc with the new formatted video
                    $this->postMediaRequestGetUrl($this->convertedFilePath);
                    $this->putMediaRequest($this->convertedFilePath);
                    while ($this->getMediaUploadStatus() != "READY") {
                        echo "Waiting for upload to complete";
                        sleep(2);
                    }
                    $this->tryCatchResponse($requestParams);
                    echo $this->response->getBody();
                    break;

                case $this->socialMediaProfiles['LINKEDINCOMPANY'];
                    $this->resolution = '400x960';
                    $this->convertVideo();
                    $this->postMediaRequestGetUrl($this->convertedFilePath);
                    $this->putMediaRequest($this->convertedFilePath);
                    while ($this->getMediaUploadStatus() != "READY") {
                        echo "Waiting for upload to complete";
                        sleep(2);
                    }
                    $this->tryCatchResponse($requestParams);
                    echo $this->response->getBody();
                    break;
            }
        }
        if(file_exists($this->convertedFilePath))
        {
            unlink($this->convertedFilePath);
        }
        return $this->response;
}

## Author ##

Ricardo Parada - ricardo.parada@publiqare.com