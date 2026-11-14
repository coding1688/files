<?php

use Coding1688\Files\ModelResolver;
use Coding1688\Files\Models\File;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use PHPUnit\Framework\TestCase;

class CustomFile extends File {}
class RemoteFile extends File
{
    protected $connection = 'remote';
}
final class ModelResolverTest extends TestCase
{
    protected function setUp(): void
    {
        Container::setInstance(new Container);
        app()->instance('config', new Repository);
        $database = new Manager(app());
        $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'remote');
        $database->bootEloquent();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_host_model_is_selected(): void
    {
        config(['files.models.file' => CustomFile::class]);
        self::assertSame(CustomFile::class, ModelResolver::resolve('file'));
        self::assertInstanceOf(CustomFile::class, (ModelResolver::resolve('file'))::query()->getModel());
    }

    public function test_default_model_is_selected(): void
    {
        self::assertSame(File::class, ModelResolver::resolve('file'));
    }

    public function test_unrelated_models_are_rejected(): void
    {
        config(['files.models.file' => stdClass::class]);
        $this->expectException(InvalidArgumentException::class);
        ModelResolver::resolve('file');
    }

    public function test_model_connection_is_used_instead_of_default(): void
    {
        config(['files.models.file' => RemoteFile::class]);
        self::assertSame('remote', ModelResolver::connection(['file'])->getName());
    }

    public function test_cross_connection_references_are_rejected(): void
    {
        config(['files.models.file' => RemoteFile::class]);
        $this->expectException(InvalidArgumentException::class);
        ModelResolver::connection(['file', 'reference']);
    }
}
