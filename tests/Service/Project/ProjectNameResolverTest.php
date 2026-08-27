<?php

declare(strict_types=1);

namespace App\Tests\Service\Project;

use App\Service\Project\ProjectNameResolver;
use PHPUnit\Framework\TestCase;

final class ProjectNameResolverTest extends TestCase
{
    public function testResolvesNameFromSshLink(): void
    {
        $resolver = new ProjectNameResolver();

        self::assertSame('my-project', $resolver->resolve('git@github.com:acme/my-project.git'));
    }

    public function testResolvesNameFromHttpsLink(): void
    {
        $resolver = new ProjectNameResolver();

        self::assertSame('my-project', $resolver->resolve('https://github.com/acme/my-project.git'));
    }

    public function testResolvesNameWithoutGitSuffix(): void
    {
        $resolver = new ProjectNameResolver();

        self::assertSame('my-project', $resolver->resolve('git@github.com:acme/my-project'));
    }

    public function testResolvesNameWithTrailingSlash(): void
    {
        $resolver = new ProjectNameResolver();

        self::assertSame('my-project', $resolver->resolve('https://github.com/acme/my-project/'));
    }
}
