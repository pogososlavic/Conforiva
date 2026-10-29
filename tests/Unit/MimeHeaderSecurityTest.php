<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Header\ParameterizedHeader;

class MimeHeaderSecurityTest extends TestCase
{
    public static function parameterWriters(): array
    {
        return [['constructor'], ['setParameter'], ['setParameters']];
    }

    #[DataProvider('parameterWriters')]
    public function test_parameter_names_cannot_inject_an_email_header(string $writer): void
    {
        $name = "filename\r\nX-Audit-Fixture: injected\r\n";
        $this->expectException(RfcComplianceException::class);

        if ($writer === 'constructor') {
            new ParameterizedHeader('Content-Disposition', 'attachment', [$name => 'fixture.pdf']);
        } else {
            $header = new ParameterizedHeader('Content-Disposition', 'attachment');
            if ($writer === 'setParameter') {
                $header->setParameter($name, 'fixture.pdf');
            } else {
                $header->setParameters([$name => 'fixture.pdf']);
            }
        }
    }

    public function test_normal_attachment_parameters_remain_supported(): void
    {
        $header = new ParameterizedHeader('Content-Disposition', 'attachment', ['filename' => 'fixture.pdf']);
        $this->assertSame('fixture.pdf', $header->getParameter('filename'));
        $this->assertStringContainsString('filename=fixture.pdf', $header->getBodyAsString());
    }
}
