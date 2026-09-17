<?php

namespace Tests\Unit;

use App\Services\AcademicEmailChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AcademicEmailCheckerTest extends TestCase
{
    private function checker(array $extra = []): AcademicEmailChecker
    {
        $config = require __DIR__.'/../../config/academic.php';

        return new AcademicEmailChecker([...$config['domains'], ...$extra]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function academicEmails(): array
    {
        return [
            'université française' => ['x@univ-lyon1.fr'],
            'sous-domaine étudiant' => ['x@etu.univ-lyon1.fr'],
            'majuscules' => ['X@UNIV-LYON1.FR'],
            'académie' => ['x@ac-versailles.fr'],
            'américain .edu' => ['x@mit.edu'],
            'britannique .ac.uk' => ['x@ox.ac.uk'],
            'australien .edu.au' => ['x@student.unimelb.edu.au'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAcademicEmails(): array
    {
        return [
            'gmail' => ['x@gmail.com'],
            'edu en tête de domaine' => ['x@edu.fake.com'],
            'domaine scolaire en préfixe' => ['x@univ-lyon1.fr.evil.com'],
            'joker qui traverse un point' => ['x@univ-evil.com.fr'],
            'suffixe collé' => ['x@notedu'],
            'suffixe collé bis' => ['x@fakeunivac.uk'],
            'sans arobase' => ['univ-lyon1.fr'],
            'domaine vide' => ['x@'],
        ];
    }

    #[DataProvider('academicEmails')]
    public function test_recognizes_academic_emails(string $email): void
    {
        $this->assertTrue($this->checker()->isAcademic($email));
    }

    #[DataProvider('nonAcademicEmails')]
    public function test_rejects_non_academic_emails(string $email): void
    {
        $this->assertFalse($this->checker()->isAcademic($email));
    }

    public function test_extra_domains_from_config_are_recognized(): void
    {
        $this->assertFalse($this->checker()->isAcademic('x@mon-lycee.org'));
        $this->assertTrue($this->checker(['mon-lycee.org'])->isAcademic('x@mon-lycee.org'));
    }
}
