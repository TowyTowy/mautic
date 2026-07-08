<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\FormModel;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;

final class CaptchaHoneypotFunctionalTest extends MauticMysqlTestCase
{
    /**
     * A captcha field with a blank answer must render as a hidden honeypot
     * (its container carries display:none) so bots are trapped while real
     * visitors never see it.
     *
     * @see https://github.com/mautic/mautic/issues/16485
     */
    public function testCaptchaWithBlankAnswerRendersAsHiddenHoneypot(): void
    {
        $form = $this->createFormWithCaptcha('');
        $html = $this->generateHtml($form);

        $container = (new Crawler($html))->filter('#mauticform_captcha');
        Assert::assertCount(1, $container, 'The honeypot captcha container should be rendered. HTML: '.$html);
        Assert::assertStringContainsString(
            'display:none',
            (string) $container->attr('style'),
            'A blank-answer captcha must stay hidden (display:none) to work as a honeypot. HTML: '.$html
        );
    }

    /**
     * A captcha field with an actual answer is a real, visible challenge and
     * must not be hidden.
     */
    public function testCaptchaWithAnswerRemainsVisible(): void
    {
        $form = $this->createFormWithCaptcha('Prague');
        $html = $this->generateHtml($form);

        $container = (new Crawler($html))->filter('#mauticform_captcha');
        Assert::assertCount(1, $container, 'The captcha container should be rendered. HTML: '.$html);
        Assert::assertStringNotContainsString(
            'display:none',
            (string) $container->attr('style'),
            'A captcha with an answer must remain visible. HTML: '.$html
        );
    }

    private function createFormWithCaptcha(string $answer): Form
    {
        $captcha = new Field();
        $captcha->setAlias('captcha');
        $captcha->setLabel('What is the capital of the Czech Republic?');
        $captcha->setType('captcha');
        $captcha->setProperties(['captcha' => $answer]);

        $form = new Form();
        $form->setName('Captcha honeypot test');
        $form->setAlias('captcha-honeypot-test');
        $form->addField(0, $captcha);
        $captcha->setForm($form);

        $this->em->persist($captcha);
        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    private function generateHtml(Form $form): string
    {
        /** @var FormModel $formModel */
        $formModel = static::getContainer()->get('mautic.form.model.form');

        return $formModel->generateHtml($form, false);
    }
}
