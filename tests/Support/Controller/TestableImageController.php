<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use Image;
use ImageType;
use Hook;
use Product;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Jtl\Connector\Core\Model\AbstractImage;
use jtl\Connector\Presta\Controller\ImageController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;

/**
 * Testable subclass that:
 * - avoids \Db::getInstance() in the constructor
 * - stubs language-lookup methods so DB is not needed for ISO conversions
 * - exposes protected methods for direct testing
 */
final class TestableImageController extends ImageController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'ImageController';
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return 'eng';
    }

    protected function getPrestaContextLanguageId(): int
    {
        return 1;
    }

    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        return 1;
    }

    public function exposeCreateJtlImage(array $image): AbstractImage
    {
        return $this->createJtlImage($image);
    }

    public function exposeGetPrestaImageI18n(AbstractImage $image): array
    {
        return $this->getPrestaImageI18n($image);
    }

    public function exposeCreateJtlImageI18ns(AbstractImage $image): array
    {
        return $this->createJtlImageI18ns($image);
    }

    public function exposeCreatePrestaCategoryImage(AbstractImage $jtlImage, bool $hightDpi, string $id): AbstractImage
    {
        return $this->createPrestaCategoryImage($jtlImage, $hightDpi, $id);
    }

    public function exposeCreatePrestaManufacturerImage(AbstractImage $jtlImage, bool $hightDpi, string $id): AbstractImage
    {
        return $this->createPrestaManufacturerImage($jtlImage, $hightDpi, $id);
    }

    public function exposeCreatePrestaProductImage(AbstractImage $jtlImage, string $id): AbstractImage
    {
        return $this->createPrestaProductImage($jtlImage, $id);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function injectMapper(PrimaryKeyMapper $mapper): void
    {
        $this->mapper = $mapper;
    }

    public function injectLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
