<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\web;

use yii\http\MemoryStream;
use yii\http\UploadedFile;
use yii\web\Request;
use yii\web\UnsupportedMediaTypeHttpException;
use yiiunit\TestCase;

/**
 * @group web
 */
class RequestTest extends TestCase
{
    public function testParseAcceptHeader()
    {
        $request = new Request();

        $this->assertEquals([], $request->parseAcceptHeader(' '));

        $this->assertEquals([
            'audio/basic' => ['q' => 1],
            'audio/*' => ['q' => 0.2],
        ], $request->parseAcceptHeader('audio/*; q=0.2, audio/basic'));

        $this->assertEquals([
            'application/json' => ['q' => 1, 'version' => '1.0'],
            'application/xml' => ['q' => 1, 'version' => '2.0', 'x'],
            'text/x-c' => ['q' => 1],
            'text/x-dvi' => ['q' => 0.8],
            'text/plain' => ['q' => 0.5],
        ], $request->parseAcceptHeader('text/plain; q=0.5,
            application/json; version=1.0,
            application/xml; version=2.0; x,
            text/x-dvi; q=0.8, text/x-c'));
    }

    public function testPrefferedLanguage()
    {
        $this->mockApplication([
            'language' => 'en',
        ]);

        $request = new Request();
        $request->acceptableLanguages = [];
        $this->assertEquals('en', $request->getPreferredLanguage());

        $request = new Request();
        $request->acceptableLanguages = ['de'];
        $this->assertEquals('en', $request->getPreferredLanguage());

        $request = new Request();
        $request->acceptableLanguages = ['en-us', 'de', 'ru-RU'];
        $this->assertEquals('en', $request->getPreferredLanguage(['en']));

        $request = new Request();
        $request->acceptableLanguages = ['en-us', 'de', 'ru-RU'];
        $this->assertEquals('de', $request->getPreferredLanguage(['ru', 'de']));
        $this->assertEquals('de-DE', $request->getPreferredLanguage(['ru', 'de-DE']));

        $request = new Request();
        $request->acceptableLanguages = ['en-us', 'de', 'ru-RU'];
        $this->assertEquals('de', $request->getPreferredLanguage(['de', 'ru']));

        $request = new Request();
        $request->acceptableLanguages = ['en-us', 'de', 'ru-RU'];
        $this->assertEquals('ru-ru', $request->getPreferredLanguage(['ru-ru']));

        $request = new Request();
        $request->acceptableLanguages = ['en-us', 'de'];
        $this->assertEquals('ru-ru', $request->getPreferredLanguage(['ru-ru', 'pl']));
        $this->assertEquals('ru-RU', $request->getPreferredLanguage(['ru-RU', 'pl']));

        $request = new Request();
        $request->acceptableLanguages = ['en-us', 'de'];
        $this->assertEquals('pl', $request->getPreferredLanguage(['pl', 'ru-ru']));
    }

    public function testCsrfTokenValidation()
    {
        $this->mockWebApplication();

        $request = new Request();
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->enableCsrfCookie = false;

        $token = $request->getCsrfToken();

        // accept any value if CSRF validation is disabled
        $request->enableCsrfValidation = false;
        $this->assertTrue($request->validateCsrfToken($token));
        $this->assertTrue($request->validateCsrfToken($token . 'a'));
        $this->assertTrue($request->validateCsrfToken([]));
        $this->assertTrue($request->validateCsrfToken([$token]));
        $this->assertTrue($request->validateCsrfToken(0));
        $this->assertTrue($request->validateCsrfToken(null));

        // enable validation
        $request->enableCsrfValidation = true;

        // accept any value on GET request
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $request->setMethod($method);
            $this->assertTrue($request->validateCsrfToken($token));
            $this->assertTrue($request->validateCsrfToken($token . 'a'));
            $this->assertTrue($request->validateCsrfToken([]));
            $this->assertTrue($request->validateCsrfToken([$token]));
            $this->assertTrue($request->validateCsrfToken(0));
            $this->assertTrue($request->validateCsrfToken(null));
        }

        // only accept valid token on POST
        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $request->setMethod($method);
            $this->assertTrue($request->validateCsrfToken($token));
            $this->assertFalse($request->validateCsrfToken($token . 'a'));
            $this->assertFalse($request->validateCsrfToken([]));
            $this->assertFalse($request->validateCsrfToken([$token]));
            $this->assertFalse($request->validateCsrfToken(0));
            $this->assertFalse($request->validateCsrfToken(null));
        }
    }

    /**
     * test CSRF token validation by POST param
     */
    public function testCsrfTokenPost()
    {
        $this->mockWebApplication();

        $request = new Request();
        $request->enableCsrfCookie = false;

        $token = $request->getCsrfToken();

        // accept no value on GET request
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $request->setMethod($method);
            $this->assertTrue($request->validateCsrfToken());
        }

        // only accept valid token on POST
        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $request->setMethod($method);
            $request->setBodyParams([]);
            $this->assertFalse($request->validateCsrfToken());
            $request->setBodyParams([$request->csrfParam => $token]);
            $this->assertTrue($request->validateCsrfToken());
        }
    }

    /**
     * test CSRF token validation by POST param
     */
    public function testCsrfTokenHeader()
    {
        $this->mockWebApplication();

        $request = new Request();
        $request->enableCsrfCookie = false;

        $token = $request->getCsrfToken();

        // accept no value on GET request
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $_POST[$request->methodParam] = $method;
            $this->assertTrue($request->validateCsrfToken());
        }

        // only accept valid token on POST
        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $request->setMethod($method);
            $request->setBodyParams([]);

            $this->assertFalse($request->withoutHeader(Request::CSRF_HEADER)->validateCsrfToken());
            $this->assertTrue($request->withAddedHeader(Request::CSRF_HEADER, $token)->validateCsrfToken());
        }
    }

    public function testResolve()
    {
        $this->mockWebApplication([
            'components' => [
                'urlManager' => [
                    'enablePrettyUrl' => true,
                    'showScriptName' => false,
                    'cache' => null,
                    'rules' => [
                        'posts' => 'post/list',
                        'post/<id>' => 'post/view',
                    ],
                ],
            ],
        ]);

        $request = new Request();
        $request->pathInfo = 'posts';

        $_GET['page'] = 1;
        $result = $request->resolve();
        $this->assertEquals(['post/list', ['page' => 1]], $result);
        $this->assertEquals($_GET, ['page' => 1]);

        $request->setQueryParams(['page' => 5]);
        $result = $request->resolve();
        $this->assertEquals(['post/list', ['page' => 5]], $result);
        $this->assertEquals($_GET, ['page' => 1]);

        $request->setQueryParams(['custom-page' => 5]);
        $result = $request->resolve();
        $this->assertEquals(['post/list', ['custom-page' => 5]], $result);
        $this->assertEquals($_GET, ['page' => 1]);

        unset($_GET['page']);

        $request = new Request();
        $request->pathInfo = 'post/21';

        $this->assertEquals($_GET, []);
        $result = $request->resolve();
        $this->assertEquals(['post/view', ['id' => 21]], $result);
        $this->assertEquals($_GET, ['id' => 21]);

        $_GET['id'] = 42;
        $result = $request->resolve();
        $this->assertEquals(['post/view', ['id' => 21]], $result);
        $this->assertEquals($_GET, ['id' => 21]);

        $_GET['id'] = 63;
        $request->setQueryParams(['token' => 'secret']);
        $result = $request->resolve();
        $this->assertEquals(['post/view', ['id' => 21, 'token' => 'secret']], $result);
        $this->assertEquals($_GET, ['id' => 63]);
    }

    public function testGetHostInfo()
    {
        $request = new Request();

        unset($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']);
        $this->assertNull($request->getHostInfo());
        $this->assertNull($request->getHostName());

        $request->setHostInfo('http://servername.com:80');
        $this->assertSame('http://servername.com:80', $request->getHostInfo());
        $this->assertSame('servername.com', $request->getHostName());
    }

    /**
     * @expectedException \yii\base\InvalidConfigException
     */
    public function testGetScriptFileWithEmptyServer()
    {
        $request = new Request();
        $_SERVER = [];

        $request->getScriptFile();
    }

    /**
     * @expectedException \yii\base\InvalidConfigException
     */
    public function testGetScriptUrlWithEmptyServer()
    {
        $request = new Request();
        $_SERVER = [];

        $request->getScriptUrl();
    }

    public function testGetServerName()
    {
        $request = new Request();

        $_SERVER['SERVER_NAME'] = 'servername';
        $this->assertEquals('servername', $request->getServerName());

        unset($_SERVER['SERVER_NAME']);
        $this->assertEquals(null, $request->getServerName());
    }

    public function testGetServerPort()
    {
        $request = new Request();

        $_SERVER['SERVER_PORT'] = 33;
        $this->assertEquals(33, $request->getServerPort());

        unset($_SERVER['SERVER_PORT']);
        $this->assertEquals(null, $request->getServerPort());
    }

    public function testGetOrigin()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://www.w3.org';
        $request = new Request();
        $this->assertEquals('https://www.w3.org', $request->getOrigin());

        unset($_SERVER['HTTP_ORIGIN']);
        $request = new Request();
        $this->assertEquals(null, $request->getOrigin());
    }

    public function testGetBodyParams()
    {
        $body = new MemoryStream();
        $body->write('name=value');

        $request = new Request();
        $request->setMethod('PUT');
        $request->setBody($body);
        $_POST = ['name' => 'post'];

        $this->assertSame(['name' => 'value'], $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')->getBodyParams());
        $this->assertSame(['name' => 'post'], $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')->withMethod('POST')->getBodyParams());
        $this->assertSame(['name' => 'post'], $request->withHeader('Content-Type', 'multipart/form-data')->withMethod('POST')->getBodyParams());

        try {
            $request->getBodyParams();
        } catch (UnsupportedMediaTypeHttpException $noContentTypeException) {}
        $this->assertTrue(isset($noContentTypeException));

        try {
            $request->withMethod('POST')->getBodyParams();
        } catch (UnsupportedMediaTypeHttpException $postWithoutContentTypeException) {}
        $this->assertTrue(isset($postWithoutContentTypeException));
    }

    /**
     * Data provider for [[testDefaultUploadedFiles()]]
     * @return array test data.
     */
    public function dataProviderDefaultUploadedFiles()
    {
        return [
            [
                [],
                [],
            ],
            [
                [
                    'avatar' => [
                        'tmp_name' => 'avatar.tmp',
                        'name' => 'my-avatar.png',
                        'size' => 90996,
                        'type' => 'image/png',
                        'error' => 0,
                    ],
                ],
                [
                    'avatar' => new UploadedFile([
                        'tempFilename' => 'avatar.tmp',
                        'clientFilename' => 'my-avatar.png',
                        'size' => 90996,
                        'clientMediaType' => 'image/png',
                        'error' => 0,
                    ])
                ]
            ],
            [
                [
                    'ItemFile' => [
                        'name' => [
                            0 => 'file0.txt',
                            1 => 'file1.txt',
                        ],
                        'type' => [
                            0 => 'type/0',
                            1 => 'type/1',
                        ],
                        'tmp_name' => [
                            0 => 'file0.tmp',
                            1 => 'file1.tmp',
                        ],
                        'size' => [
                            0 => 1000,
                            1 => 1001,
                        ],
                        'error' => [
                            0 => 0,
                            1 => 1,
                        ],
                    ],
                ],
                [
                    'ItemFile' => [
                        0 => new UploadedFile([
                            'clientFilename' => 'file0.txt',
                            'clientMediaType' => 'type/0',
                            'tempFilename' => 'file0.tmp',
                            'size' => 1000,
                            'error' => 0,
                        ]),
                        1 => new UploadedFile([
                            'clientFilename' => 'file1.txt',
                            'clientMediaType' => 'type/1',
                            'tempFilename' => 'file1.tmp',
                            'size' => 1001,
                            'error' => 1,
                        ]),
                    ],
                ],
            ],
            [
                [
                    'my-form' => [
                        'name' => [
                            'details' => [
                                'avatar' => 'my-avatar.png'
                            ],
                        ],
                        'tmp_name' => [
                            'details' => [
                                'avatar' => 'avatar.tmp'
                            ],
                        ],
                        'size' => [
                            'details' => [
                                'avatar' => 90996
                            ],
                        ],
                        'type' => [
                            'details' => [
                                'avatar' => 'image/png'
                            ],
                        ],
                        'error' => [
                            'details' => [
                                'avatar' => 0
                            ],
                        ],
                    ],
                ],
                [
                    'my-form' => [
                        'details' => [
                            'avatar' => new UploadedFile([
                                'tempFilename' => 'avatar.tmp',
                                'clientFilename' => 'my-avatar.png',
                                'clientMediaType' => 'image/png',
                                'size' => 90996,
                                'error' => 0,
                            ])
                        ],
                    ],
                ]
            ],
        ];
    }

    /**
     * @depends testGetBodyParams
     * @dataProvider dataProviderDefaultUploadedFiles
     *
     * @param array $rawFiles
     * @param array $expectedFiles
     */
    public function testDefaultUploadedFiles(array $rawFiles, array $expectedFiles)
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->setHeader('Content-Type', 'multipart/form-data');

        $_FILES = $rawFiles;

        $this->assertEquals($expectedFiles, $request->getUploadedFiles());
    }

    /**
     * @depends testDefaultUploadedFiles
     */
    public function testGetUploadedFileByName()
    {
        $request = new Request();
        $request->setUploadedFiles([
            'ItemFile' => [
                0 => new UploadedFile([
                    'clientFilename' => 'file0.txt',
                    'clientMediaType' => 'type/0',
                    'tempFilename' => 'file0.tmp',
                    'size' => 1000,
                    'error' => 0,
                ]),
                1 => new UploadedFile([
                    'clientFilename' => 'file1.txt',
                    'clientMediaType' => 'type/1',
                    'tempFilename' => 'file1.tmp',
                    'size' => 1001,
                    'error' => 1,
                ]),
            ],
        ]);

        /* @var $uploadedFile UploadedFile */
        $uploadedFile = $request->getUploadedFileByName('ItemFile[0]');
        $this->assertTrue($uploadedFile instanceof UploadedFile);
        $this->assertSame('file0.txt', $uploadedFile->getClientFilename());
        $this->assertSame($uploadedFile, $request->getUploadedFileByName(['ItemFile', 0]));

        $this->assertNull($request->getUploadedFileByName('ItemFile[3]'));
        $this->assertNull($request->getUploadedFileByName(['ItemFile', 3]));
    }

    /**
     * @depends testGetUploadedFileByName
     */
    public function testGetUploadedFilesByName()
    {
        $request = new Request();
        $request->setUploadedFiles([
            'Item' => [
                'file' => [
                    0 => new UploadedFile([
                        'clientFilename' => 'file0.txt',
                        'clientMediaType' => 'type/0',
                        'tempFilename' => 'file0.tmp',
                        'size' => 1000,
                        'error' => 0,
                    ]),
                    1 => new UploadedFile([
                        'clientFilename' => 'file1.txt',
                        'clientMediaType' => 'type/1',
                        'tempFilename' => 'file1.tmp',
                        'size' => 1001,
                        'error' => 1,
                    ]),
                ],
            ],
        ]);

        $uploadedFiles = $request->getUploadedFilesByName('Item[file]');
        $this->assertCount(2, $uploadedFiles);
        $this->assertTrue($uploadedFiles[0] instanceof UploadedFile);
        $this->assertTrue($uploadedFiles[1] instanceof UploadedFile);

        $uploadedFiles = $request->getUploadedFilesByName('Item');
        $this->assertCount(2, $uploadedFiles);
        $this->assertTrue($uploadedFiles[0] instanceof UploadedFile);
        $this->assertTrue($uploadedFiles[1] instanceof UploadedFile);

        $uploadedFiles = $request->getUploadedFilesByName('Item[file][0]');
        $this->assertCount(1, $uploadedFiles);
        $this->assertTrue($uploadedFiles[0] instanceof UploadedFile);
    }

    public function testSetupPathInfo()
    {
        $request = new Request();

        $request->setPathInfo(['some', 'path']);
        $this->assertSame(['some', 'path'], $request->getPathInfo());

        $request->setPathInfo('some/path');
        $this->assertSame(['some', 'path'], $request->getPathInfo());

        $request->setPathInfo('some/path/');
        $this->assertSame(['some', 'path', ''], $request->getPathInfo());

        $request->setPathInfo('/some/path/');
        $this->assertSame(['some', 'path', ''], $request->getPathInfo());

        $request->setPathInfo('');
        $this->assertSame([], $request->getPathInfo());

        $request->setPathInfo('/');
        $this->assertSame([''], $request->getPathInfo());
    }

    /**
     * Data provider for [[testResolvePathInfo()]]
     * @return array test data
     */
    public function dataProviderResolvePathInfo()
    {
        return [
            [
                '/path/project/index.php',
                '/path/project',
                '/path/project/index.php',
                '/path/project/index.php',
                []
            ],
            [
                '/path/project/index.php/some/path',
                '/path/project',
                '/path/project/index.php',
                '/path/project/index.php',
                ['some', 'path']
            ],
            [
                '/path/project/some/path',
                '/path/project',
                '/path/project/index.php',
                '/path/project/index.php',
                ['some', 'path']
            ],
            [
                '/path/project/some/path/',
                '/path/project',
                '/path/project/index.php',
                '/path/project/index.php',
                ['some', 'path', '']
            ],
            [
                '/path/project/some%2fpath',
                '/path/project',
                '/path/project/index.php',
                '/path/project/index.php',
                ['some/path']
            ],
        ];
    }

    /**
     * @dataProvider dataProviderResolvePathInfo
     *
     * @param string $url
     * @param string $baseUrl
     * @param string $scriptUrl
     * @param string $phpSelf
     * @param string $expectedPathInfo
     */
    public function testResolvePathInfo($url, $baseUrl, $scriptUrl, $phpSelf, $expectedPathInfo)
    {
        $_SERVER['PHP_SELF'] = $phpSelf;

        $request = new Request([
            'url' => $url,
            'baseUrl' => $baseUrl,
            'scriptUrl' => $scriptUrl,
        ]);

        $this->assertSame($expectedPathInfo, $request->getPathInfo());
    }
}
