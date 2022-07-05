<?php declare(strict_types=1);
/**
 * @package   Elabftw\Elabftw
 * @author    Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @license   https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @see       https://www.elabftw.net Official website
 */

namespace Elabftw\Services;

use Elabftw\Elabftw\Tools;
use Elabftw\Factories\StorageFactory;
use Elabftw\Interfaces\PdfMakerInterface;
use Elabftw\Interfaces\ZipMakerInterface;
use Elabftw\Models\Items;
use Elabftw\Models\ItemsTypes;
use Elabftw\Models\Templates;
use ZipStream\ZipStream;

/**
 * Mother class of the Make*Zip services
 */
abstract class AbstractMakeZip extends AbstractMake implements ZipMakerInterface
{
    protected ZipStream $Zip;

    protected string $folder = '';

    protected string $contentType = 'application/zip';

    protected string $extension = '.zip';

    /**
     * Folder and zip file name begins with date for experiments
     */
    protected function getBaseFileName(): string
    {
        // items will show category instead of date as file name prefix
        if ($this->Entity instanceof Items || $this->Entity instanceof ItemsTypes) {
            $prefix = 'category';
        } elseif ($this->Entity instanceof Templates) {
            $prefix = 'Experiment template';
        } else { // Experiments
            $prefix = 'date';
        }

        return sprintf(
            '%s - %s - %s',
            // category is user input, better filter it
            Filter::forFilesystem($this->Entity->entityData[$prefix]),
            Filter::forFilesystem($this->Entity->entityData['title']),
            Tools::getShortElabid($this->Entity->entityData['elabid'] ?? ''),
        );
    }

    /**
     * Add attached files
     *
     * @param array<array-key, array<string, string>> $filesArr the files array
     */
    protected function addAttachedFiles($filesArr): array
    {
        $realNamesSoFar = array();
        $i = 0;
        foreach ($filesArr as &$file) {
            $i++;
            $realName = $file['real_name'];
            // if we have a file with the same name, it shouldn't overwrite the previous one
            if (in_array($realName, $realNamesSoFar, true)) {
                $realName = (string) $i . '_' . $realName;
            }
            $realNamesSoFar[] = $realName;
            // modify the real_name in place
            $file['real_name'] = $realName;

            // add files to archive
            $storageFs = (new StorageFactory((int) $file['storage']))->getStorage()->getFs();
            $this->Zip->addFileFromStream($this->folder . '/' . $realName, $storageFs->readStream($file['long_name']));
        }
        return $filesArr;
    }

    protected function getPdf(): PdfMakerInterface
    {
        $userData = $this->Entity->Users->userData;
        $MpdfProvider = new MpdfProvider(
            $userData['fullname'],
            $userData['pdf_format'],
            (bool) $userData['pdfa'],
        );
        return new MakePdf($MpdfProvider, $this->Entity);
    }

    /**
     * Add a PDF file to the ZIP archive
     */
    protected function addPdf(): void
    {
        $MakePdf = $this->getPdf();
        // disable makepdf notifications because they are handled by calling class
        $MakePdf->setNotifications(false);
        $this->Zip->addFile($this->folder . '/' . $MakePdf->getFileName(), $MakePdf->getFileContent());
    }
}
