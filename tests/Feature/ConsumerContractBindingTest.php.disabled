<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Nvl\Pages\Actions\CheckPageKeyAvailabilityAction;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Actions\DeletePageAction;
use Nvl\Pages\Actions\FindPageByKeyAction;
use Nvl\Pages\Actions\GetNavigationAction;
use Nvl\Pages\Actions\GetPageAction;
use Nvl\Pages\Actions\GetPageEditorBootstrapAction;
use Nvl\Pages\Actions\GetPagePublicationProjectionAction;
use Nvl\Pages\Actions\ListPageEditorSummariesAction;
use Nvl\Pages\Actions\ListPageOptionsAction;
use Nvl\Pages\Actions\ListPagesAction;
use Nvl\Pages\Actions\ListPublicChildPagesAction;
use Nvl\Pages\Actions\MovePageAction;
use Nvl\Pages\Actions\PreviewPageAction;
use Nvl\Pages\Actions\ResolvePageAction;
use Nvl\Pages\Actions\RestorePageAction;
use Nvl\Pages\Actions\UpdatePageAction;
use Nvl\Pages\Contracts\CheckPageKeyAvailabilityContract;
use Nvl\Pages\Contracts\CreatePageContract;
use Nvl\Pages\Contracts\DeletePageContract;
use Nvl\Pages\Contracts\FindPageByKeyContract;
use Nvl\Pages\Contracts\GetNavigationContract;
use Nvl\Pages\Contracts\GetPageContract;
use Nvl\Pages\Contracts\GetPageEditorBootstrapContract;
use Nvl\Pages\Contracts\GetPagePublicationProjectionContract;
use Nvl\Pages\Contracts\ListPageEditorSummariesContract;
use Nvl\Pages\Contracts\ListPageOptionsContract;
use Nvl\Pages\Contracts\ListPagesContract;
use Nvl\Pages\Contracts\ListPublicChildPagesContract;
use Nvl\Pages\Contracts\MovePageContract;
use Nvl\Pages\Contracts\PreviewPageContract;
use Nvl\Pages\Contracts\ResolvePageContract;
use Nvl\Pages\Contracts\RestorePageContract;
use Nvl\Pages\Contracts\UpdatePageContract;
use Nvl\Pages\Providers\PagesServiceProvider;
use Nvl\Pages\Tests\TestCase;

if (! in_array(dirname(__DIR__).'/Pest.php', get_included_files(), true)) {
    uses(TestCase::class);
}

/** @return list<array{class-string, class-string}> */
function nvlConsumerBindingsForPages(): array
{
    return [
        [CheckPageKeyAvailabilityContract::class, CheckPageKeyAvailabilityAction::class],
        [CreatePageContract::class, CreatePageAction::class],
        [DeletePageContract::class, DeletePageAction::class],
        [FindPageByKeyContract::class, FindPageByKeyAction::class],
        [GetNavigationContract::class, GetNavigationAction::class],
        [GetPageContract::class, GetPageAction::class],
        [GetPageEditorBootstrapContract::class, GetPageEditorBootstrapAction::class],
        [GetPagePublicationProjectionContract::class, GetPagePublicationProjectionAction::class],
        [ListPageEditorSummariesContract::class, ListPageEditorSummariesAction::class],
        [ListPageOptionsContract::class, ListPageOptionsAction::class],
        [ListPagesContract::class, ListPagesAction::class],
        [ListPublicChildPagesContract::class, ListPublicChildPagesAction::class],
        [MovePageContract::class, MovePageAction::class],
        [PreviewPageContract::class, PreviewPageAction::class],
        [ResolvePageContract::class, ResolvePageAction::class],
        [RestorePageContract::class, RestorePageAction::class],
        [UpdatePageContract::class, UpdatePageAction::class],
    ];
}

test('published workflow contracts retain native signatures attributes and generic documentation', function (): void {
    $genericDocumentation = static function (string|false $documentation): array {
        if ($documentation === false) {
            return [];
        }
        preg_match_all('/@param\s+([^\r\n]+?)\s+(\$[A-Za-z_][A-Za-z0-9_]*)\b/', $documentation, $parameters, PREG_SET_ORDER);
        $result = [];
        foreach ($parameters as $parameter) {
            $type = preg_replace('/\s+/', '', $parameter[1]);
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@param'.$parameter[2]] = $type;
            }
        }
        if (preg_match('/@return\s+([^\r\n]+)/', $documentation, $return) === 1) {
            $type = '';
            $depth = 0;
            foreach (str_split($return[1]) as $character) {
                if (preg_match('/\s/', $character) === 1 && $depth === 0) {
                    break;
                }
                if (str_contains('<{([', $character)) {
                    $depth++;
                } elseif (str_contains('>})]', $character)) {
                    $depth--;
                }
                if (preg_match('/\s/', $character) !== 1) {
                    $type .= $character;
                }
            }
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@return'] = $type;
            }
        }

        return $result;
    };

    foreach (nvlConsumerBindingsForPages() as [$contract, $implementation]) {
        $interface = new ReflectionClass($contract);
        $concrete = new ReflectionClass($implementation);
        expect($interface->isInterface())->toBeTrue()
            ->and($concrete->implementsInterface($contract))->toBeTrue();
        foreach ($interface->getMethods() as $method) {
            $native = $concrete->getMethod($method->getName());
            $return = (string) $method->getReturnType();

            $publishedTypes = $genericDocumentation($method->getDocComment());
            foreach ($genericDocumentation($native->getDocComment()) as $tag => $type) {
                expect($publishedTypes[$tag] ?? null)->toBe($type);
            }

            expect($native->isPublic())->toBeTrue()
                ->and($native->isStatic())->toBeFalse()
                ->and(count($method->getParameters()))->toBe(count($native->getParameters()));
            if ($return !== 'self') {
                expect((string) $native->getReturnType())->toBe($return);
            } else {
                $nativeReturn = (string) $native->getReturnType();
                expect(is_a(in_array($nativeReturn, ['self', 'static'], true) ? $native->getDeclaringClass()->getName() : $nativeReturn, $contract, true))->toBeTrue();
            }
            foreach ($method->getParameters() as $position => $parameter) {
                $actual = $native->getParameters()[$position];
                expect($actual->getName())->toBe($parameter->getName())
                    ->and((string) $actual->getType())->toBe((string) $parameter->getType())
                    ->and($actual->isVariadic())->toBe($parameter->isVariadic())
                    ->and($actual->isPassedByReference())->toBe($parameter->isPassedByReference())
                    ->and($actual->isDefaultValueAvailable())->toBe($parameter->isDefaultValueAvailable())
                    ->and(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $actual->getAttributes()))
                    ->toBe(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $parameter->getAttributes()));
                if ($parameter->isDefaultValueAvailable()) {
                    expect($actual->getDefaultValue())->toEqual($parameter->getDefaultValue());
                }
            }
        }
    }
});

test('native provider defaults resolve each workflow while preserving late host substitutes', function (): void {
    foreach (nvlConsumerBindingsForPages() as [$contract, $implementation]) {
        expect($this->app->bound($contract))->toBeTrue()
            ->and($this->app->make($contract))->toBeInstanceOf($implementation);
        $host = Mockery::mock($contract);
        $this->app->instance($contract, $host);
        expect($this->app->make($contract))->toBe($host);
    }
});

test('provider registration preserves early interface bindings in a second native application', function (): void {
    $consumer = new Application($this->app->basePath());
    $consumer->instance('config', new Repository($this->app->make('config')->all()));
    $consumer->instance('env', 'testing');
    $consumer->register(FilesystemServiceProvider::class);
    $hosts = [];
    foreach (nvlConsumerBindingsForPages() as [$contract]) {
        $hosts[$contract] = Mockery::mock($contract);
        $consumer->instance($contract, $hosts[$contract]);
    }
    try {
        $consumer->register(PagesServiceProvider::class);
        foreach ($hosts as $contract => $host) {
            expect($consumer->make($contract))->toBe($host);
        }
    } finally {
        Container::setInstance($this->app);
        $consumer->flush();
    }
});
