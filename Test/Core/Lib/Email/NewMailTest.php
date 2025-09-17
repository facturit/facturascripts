<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Test\Core\Lib\Email;

use FacturaScripts\Core\Lib\Email\NewMail;
use FacturaScripts\Core\Tools;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

final class NewMailTest extends TestCase
{
    public function testCreate(): void
    {
        $mailer = NewMail::create()
            ->to('test@facturascripts.com', 'test name')
            ->subject('test subject')
            ->body('test body');

        $this->assertInstanceOf(NewMail::class, $mailer);

        $this->assertEquals('test subject', $mailer->title);
        $this->assertEquals('test body', $mailer->text);

        $this->assertCount(1, $mailer->getToAddresses());
        $this->assertContains('test@facturascripts.com', $mailer->getToAddresses());

        $this->assertEmpty($mailer->getCcAddresses());
        $this->assertEmpty($mailer->getBccAddresses());
    }

    public function testCC(): void
    {
        $mailer = NewMail::create()
            ->cc('test-cc@facturascripts.com', 'test cc name')
            ->subject('cc subject')
            ->body('cc body');

        $this->assertEmpty($mailer->getToAddresses());
        $this->assertCount(1, $mailer->getCcAddresses());
        $this->assertContains('test-cc@facturascripts.com', $mailer->getCcAddresses());
        $this->assertEmpty($mailer->getBccAddresses());
    }

    public function testBCC(): void
    {
        $mailer = NewMail::create()
            ->bcc('test-bcc@facturascripts.com', 'test bcc name')
            ->subject('bcc subject')
            ->body('bcc body');

        $this->assertEmpty($mailer->getToAddresses());
        $this->assertEmpty($mailer->getCcAddresses());
        $this->assertCount(1, $mailer->getBccAddresses());
    }

    public function testCanSendMailWithXOAUTH2RequiresCredentials(): void
    {
        Tools::settingsClear();
        Tools::settingsSet('email', 'email', 'user@example.com');
        Tools::settingsSet('email', 'host', 'smtp.office365.com');
        Tools::settingsSet('email', 'authtype', 'XOAUTH2');

        $mailer = NewMail::create();

        $this->assertFalse($mailer->canSendMail());
    }

    public function testCanSendMailWithXOAUTH2AndCredentials(): void
    {
        $this->configureXOAUTH2Settings();

        $mailer = NewMail::create();

        $this->assertTrue($mailer->canSendMail());
    }

    public function testSendWithXOAUTH2UsesOAuthProvider(): void
    {
        $this->configureXOAUTH2Settings();

        $provider = new \stdClass();
        $token = new DummyAccessToken('refresh-token');

        $mailer = new NewMailOAuthTestDouble();
        $mailer->provider = $provider;
        $mailer->token = $token;

        $mailStub = new TestMailerOAuthPHPMailer();
        $mailStub->Mailer = 'SMTP';
        $mailStub->Host = Tools::settings('email', 'host');
        $mailStub->AuthType = 'XOAUTH2';
        $mailStub->SMTPAuth = true;
        $mailStub->Username = Tools::settings('email', 'user');

        $property = new \ReflectionProperty(NewMail::class, 'mail');
        $property->setAccessible(true);
        $property->setValue($mailer, $mailStub);

        $this->assertTrue($mailer->send());
        $this->assertTrue($mailer->createOAuthProviderCalled);
        $this->assertTrue($mailer->requestOAuthAccessTokenCalled);
        $this->assertIsObject($mailStub->oauthConfig);
    }

    private function configureXOAUTH2Settings(): void
    {
        Tools::settingsClear();
        Tools::settingsSet('email', 'email', 'user@example.com');
        Tools::settingsSet('email', 'user', 'user@example.com');
        Tools::settingsSet('email', 'host', 'smtp.office365.com');
        Tools::settingsSet('email', 'mailer', 'SMTP');
        Tools::settingsSet('email', 'port', '587');
        Tools::settingsSet('email', 'enc', 'tls');
        Tools::settingsSet('email', 'authtype', 'XOAUTH2');
        Tools::settingsSet('email', 'tenant_id', 'tenant');
        Tools::settingsSet('email', 'client_id', 'client');
        Tools::settingsSet('email', 'client_secret', 'secret');
        Tools::settingsSet('email', 'redirect_uri', 'https://example.com/callback');
        Tools::settingsSet('email', 'refresh_token', 'refresh-token');
        Tools::settingsSet('email', 'password', '');
    }
}

class DummyAccessToken
{
    public function __construct(private readonly ?string $refreshToken)
    {
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }
}

class NewMailOAuthTestDouble extends NewMail
{
    public $provider;
    public $token;
    public $createOAuthProviderCalled = false;
    public $requestOAuthAccessTokenCalled = false;

    protected function renderHTML(): void
    {
        $this->html = '<p>mock</p>';
    }

    protected function createOAuthProvider(array $settings)
    {
        $this->createOAuthProviderCalled = true;
        return $this->provider;
    }

    protected function requestOAuthAccessToken($provider, array $settings)
    {
        $this->requestOAuthAccessTokenCalled = true;
        return $this->token;
    }
}

class TestMailerOAuthPHPMailer extends PHPMailer
{
    public $oauthConfig;

    public function __construct()
    {
        parent::__construct(true);
    }

    public function setOAuth($oauth)
    {
        $this->oauthConfig = $oauth;
    }

    public function smtpConnect($options = null, $timeout = 300)
    {
        return true;
    }

    public function send()
    {
        return true;
    }
}
