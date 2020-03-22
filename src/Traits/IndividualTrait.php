<?php

/**
 * See LICENSE.md file for further details.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\FanChart\Traits;

use DOMNode;
use DOMXPath;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;

/**
 * Trait IndividualTrait.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-fan-chart/
 */
trait IndividualTrait
{
    /**
     * The XPath identifiers to extract the name parts.
     */
    private $xpathName          = '//span[contains(attribute::class, "NAME")]';
    private $xpathNickname      = '//q[contains(attribute::class, "wt-nickname")]';
    private $xpathSurname       = '//span[contains(attribute::class, "SURN")]';
    private $xpathPreferredName = '//span[contains(attribute::class, "starredname")]';

    /**
     * Get the individual data required for display the chart.
     *
     * @param Individual $individual The current individual
     * @param int        $generation The generation the person belongs to
     *
     * @return string[][]
     */
    private function getIndividualData(Individual $individual, int $generation): array
    {
        $primaryName = $individual->getAllNames()[$individual->getPrimaryName()];

        // The formatted name of the individual (containing HTML)
        $full = $primaryName['full'];

        // Get xpath
        $xpath = $this->getXPath($full);

        // The name of the person without formatting of the individual parts of the name.
        // Remove placeholders as we do not need them in this module
        $fullNN = str_replace(['@N.N.', '@P.N.'], '', $primaryName['fullNN']);

        // Extract name parts
        $preferredName    = $this->getPreferredName($xpath);
        $lastNames        = $this->getLastNames($xpath);
        $firstNames       = $this->getFirstNames($xpath);
        $alternativeNames = $this->getAlternateNames($individual);

        return [
            'id'               => 0,
            'xref'             => $individual->xref(),
            'url'              => $individual->url(),
            'updateUrl'        => $this->getUpdateRoute($individual),
            'generation'       => $generation,
            'name'             => $fullNN,
            'firstNames'       => $firstNames,
            'lastNames'        => $lastNames,
            'preferredName'    => $preferredName,
            'alternativeNames' => $alternativeNames,
            'isAltRtl'         => $this->isRtl($alternativeNames),
            'sex'              => $individual->sex(),
            'timespan'         => $this->getLifetimeDescription($individual),
            'color'            => $this->getColor($individual),
            'colors'           => [[], []],
        ];
    }

    /**
     * Returns the DOMXPath instance.
     *
     * @param string $fullName The individuals full name (containing HTML)
     *
     * @return DOMXPath
     */
    private function getXPath(string $fullName): DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadHTML(mb_convert_encoding($fullName, 'HTML-ENTITIES', 'UTF-8'));

        return new DOMXPath($document);
    }

    /**
     * Create the timespan label.
     *
     * @param Individual $individual The current individual
     *
     * @return string
     */
    private function getLifetimeDescription(Individual $individual): string
    {
        if ($individual->getBirthDate()->isOK() && $individual->getDeathDate()->isOK()) {
            return $individual->getBirthYear() . '-' . $individual->getDeathYear();
        }

        if ($individual->getBirthDate()->isOK()) {
            return I18N::translate('Born: %s', $individual->getBirthYear());
        }

        if ($individual->getDeathDate()->isOK()) {
            return I18N::translate('Died: %s', $individual->getDeathYear());
        }

        if ($individual->isDead()) {
            return I18N::translate('Deceased');
        }

        return '';
    }

    /**
     * Returns all first names from the given full name.
     *
     * @param DOMXPath $xpath The DOMXPath instance used to parse for the preferred name.
     *
     * @return string[]
     */
    public function getFirstNames(DOMXPath $xpath): array
    {
        // Remove SURN HTML nodes
        foreach ($xpath->query($this->xpathSurname) as $node) {
            $node->parentNode->removeChild($node);
        }

        // Remove nickname HTML nodes
        foreach ($xpath->query($this->xpathNickname) as $node) {
            $node->parentNode->removeChild($node);
        }

        $nodeList   = $xpath->query($this->xpathName);
        $firstNames = $nodeList->count() ? $nodeList->item(0)->nodeValue : '';

        return array_filter(explode(' ', $firstNames));
    }

    /**
     * Returns all last names from the given full name.
     *
     * @param DOMXPath $xpath The DOMXPath instance used to parse for the preferred name.
     *
     * @return string[]
     */
    public function getLastNames(DOMXPath $xpath): array
    {
        $nodeList  = $xpath->query($this->xpathSurname);
        $lastNames = [];

        /** @var DOMNode $node */
        foreach ($nodeList as $node) {
            $lastNames[] = $node->nodeValue;
        }

        return $lastNames;
    }

    /**
     * Returns the preferred name from the given full name.
     *
     * @param DOMXPath $xpath The DOMXPath instance used to parse for the preferred name.
     *
     * @return string
     */
    public function getPreferredName(DOMXPath $xpath): string
    {
        $nodeList = $xpath->query($this->xpathPreferredName);
        return $nodeList->count() ? $nodeList->item(0)->nodeValue : '';
    }

    /**
     * Returns the preferred name from the given full name.
     *
     * @param Individual $individual
     *
     * @return string[]
     */
    public function getAlternateNames(Individual $individual): array
    {
        $name = $individual->alternateName();

        if ($name === null) {
            return [];
        }

        $xpath    = $this->getXPath($name);
        $nodeList = $xpath->query($this->xpathName);
        $name     = $nodeList->count() ? $nodeList->item(0)->nodeValue : '';

        return array_filter(explode(' ', $name));
    }
}
