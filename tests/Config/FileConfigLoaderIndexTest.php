<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Config;

use CoolMS\Core\Application\Config\FileConfigLoader;
use PHPUnit\Framework\TestCase;

/**
 * A root's files are indexed once, and the index never answers with what the files no longer say.
 *
 * How often the files are parsed is seen through one unreadable file: each parse of it logs one warning. The
 * changes below are made within the same second and at the same size where that matters, since those are what a
 * file's time and size cannot show.
 */
final class FileConfigLoaderIndexTest extends TestCase
{
    /** @var list<string> */
    private array $temp = [];

    public function testIdsWithNoFileAreAnsweredFromOneParseOfTheRoot(): void
    {
        $app = $this->makeConfigDir([
            'widgets/kinds/banner.yaml' => "type: widget\nid: banner\n",
            'widgets/kinds/broken.yaml' => "type: [unclosed\n",
        ]);
        $log = new CountingLogger();
        $loader = new FileConfigLoader($app, $log);

        foreach (['a', 'b', 'c', 'd'] as $id) {
            self::assertNull($loader->load('widget', $id));
        }
        self::assertIsArray($loader->load('widget', 'banner'));

        self::assertSame(1, $log->warnings, 'five lookups parse the root once, not once each');
    }

    public function testAFileAddedAfterAMissIsFound(): void
    {
        $app = $this->makeConfigDir(['widgets/kinds/banner.yaml' => "type: widget\nid: banner\n"]);
        $loader = new FileConfigLoader($app, new CountingLogger());
        $missing = $loader->load('widget', 'hero');
        self::assertNull($missing, 'a miss, remembered');

        file_put_contents($app . '/modules/widgets/kinds/hero.yaml', "type: widget\nid: hero\nlabel: new\n");

        $hero = $loader->load('widget', 'hero');
        self::assertIsArray($hero, 'the added file is found');
        self::assertSame('new', $hero['label']);
        self::assertSame($app . '/modules/widgets/kinds/hero.yaml', $loader->locate('widget', 'hero'));
    }

    public function testAFileEditedInPlaceAtTheSameSizeIsReadAfresh(): void
    {
        $app = $this->makeConfigDir(['widgets/kinds/banner.yaml' => "type: widget\nid: banner\nlabel: aaa\n"]);
        $loader = new FileConfigLoader($app, new CountingLogger());
        $before = $loader->load('widget', 'banner');
        self::assertIsArray($before);
        self::assertSame('aaa', $before['label']);

        file_put_contents($app . '/modules/widgets/kinds/banner.yaml', "type: widget\nid: banner\nlabel: bbb\n");

        $after = $loader->load('widget', 'banner');
        self::assertIsArray($after);
        self::assertSame('bbb', $after['label']);
    }

    public function testAFileWhoseIdChangedAnswersTheNewIdAndNotTheOld(): void
    {
        $file = 'widgets/kinds/banner.yaml';
        $app = $this->makeConfigDir([$file => "type: widget\nid: aaa\n"]);
        $loader = new FileConfigLoader($app, new CountingLogger());
        self::assertIsArray($loader->load('widget', 'aaa'));
        self::assertNull($loader->load('widget', 'bbb'), 'a miss, remembered');

        file_put_contents($app . '/modules/' . $file, "type: widget\nid: bbb\n");

        // The remembered miss is asked first: asking the old id would re-read its file and rebuild the index anyway.
        self::assertIsArray($loader->load('widget', 'bbb'), 'the remembered miss is not kept past the change');
        self::assertNull($loader->load('widget', 'aaa'), 'the old id is no longer answered');
    }

    public function testARemovedFileIsNoLongerFound(): void
    {
        $app = $this->makeConfigDir(['widgets/kinds/banner.yaml' => "type: widget\nid: banner\n"]);
        $loader = new FileConfigLoader($app, new CountingLogger());
        self::assertIsArray($loader->load('widget', 'banner'));

        unlink($app . '/modules/widgets/kinds/banner.yaml');

        self::assertNull($loader->load('widget', 'banner'));
        self::assertNull($loader->locate('widget', 'banner'));
    }

    public function testTheFirstFileInOrderStillAnswersAnIdTwoFilesShare(): void
    {
        $app = $this->makeConfigDir([
            'a/kinds/one.yaml' => "type: widget\nid: banner\nlabel: first\n",
            'b/kinds/two.yaml' => "type: widget\nid: banner\nlabel: second\n",
        ]);
        $loader = new FileConfigLoader($app, new CountingLogger());

        foreach (['from the scan', 'and from the index'] as $when) {
            $loaded = $loader->load('widget', 'banner');
            self::assertIsArray($loaded, $when);
            self::assertSame('first', $loaded['label'], $when);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->temp as $dir) {
            $this->rmrf($dir);
        }
        $this->temp = [];
    }

    /** @param array<string, string> $files paths below modules/ => contents */
    private function makeConfigDir(array $files): string
    {
        $root = sys_get_temp_dir() . '/coolms-cfg-' . bin2hex(random_bytes(6));
        $this->temp[] = $root;

        foreach ($files as $rel => $body) {
            $full = $root . '/modules/' . $rel;
            $dir = dirname($full);
            if (!is_dir($dir) && !mkdir($dir, 0o777, true) && !is_dir($dir)) {
                self::fail('could not create ' . $dir);
            }
            file_put_contents($full, $body);
        }

        return $root;
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmrf($path) : unlink($path);
        }

        rmdir($dir);
    }
}
