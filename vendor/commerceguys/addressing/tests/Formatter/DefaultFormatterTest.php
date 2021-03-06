<?php

namespace CommerceGuys\Addressing\Tests\Formatter;

use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;

/**
 * @coversDefaultClass \CommerceGuys\Addressing\Formatter\DefaultFormatter
 */
class DefaultFormatterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * The address format repository.
     *
     * @var AddressFormatRepositoryInterface
     */
    protected $addressFormatRepository;

    /**
     * The country repository.
     *
     * @var CountryRepositoryInterface
     */
    protected $countryRepository;

    /**
     * The subdivision repository.
     *
     * @var SubdivisionRepositoryInterface
     */
    protected $subdivisionRepository;

    /**
     * The formatter.
     *
     * @var DefaultFormatter
     */
    protected $formatter;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->addressFormatRepository = new AddressFormatRepository();
        $this->countryRepository = new CountryRepository();
        $this->subdivisionRepository = new SubdivisionRepository();
        $this->formatter = new DefaultFormatter($this->addressFormatRepository, $this->countryRepository, $this->subdivisionRepository);
    }

    /**
     * @covers ::__construct
     */
    public function testConstructor()
    {
        $formatter = new DefaultFormatter($this->addressFormatRepository, $this->countryRepository, $this->subdivisionRepository);
        $this->assertEquals($this->addressFormatRepository, $this->getObjectAttribute($formatter, 'addressFormatRepository'));
        $this->assertEquals($this->countryRepository, $this->getObjectAttribute($formatter, 'countryRepository'));
        $this->assertEquals($this->subdivisionRepository, $this->getObjectAttribute($formatter, 'subdivisionRepository'));
    }

    /**
     * @covers ::getLocale
     * @covers ::setLocale
     */
    public function testLocale()
    {
        $formatter = new DefaultFormatter($this->addressFormatRepository, $this->countryRepository, $this->subdivisionRepository, 'en');
        $this->assertEquals('en', $formatter->getLocale());
        $formatter->setLocale('fr');
        $this->assertEquals('fr', $formatter->getLocale());
    }

    /**
     * @covers ::setOption
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidOption()
    {
        $this->formatter->setOption('invalid', 'new value');
    }

    /**
     * @covers ::getOptions
     * @covers ::setOptions
     * @covers ::getOption
     * @covers ::setOption
     * @covers ::getDefaultOptions
     */
    public function testOptions()
    {
        $formatter = new DefaultFormatter($this->addressFormatRepository, $this->countryRepository, $this->subdivisionRepository, 'en', ['html' => false]);

        $expectedOptions = [
            'html' => false,
            'html_tag' => 'p',
            'html_attributes' => ['translate' => 'no'],
        ];
        $this->assertEquals($expectedOptions, $formatter->getOptions());
        $this->assertEquals('p', $formatter->getOption('html_tag'));
        $formatter->setOption('html_tag', 'div');
        $this->assertEquals('div', $formatter->getOption('html_tag'));
    }

    /**
     * @covers \CommerceGuys\Addressing\Formatter\DefaultFormatter
     */
    public function testAndorraAddress()
    {
        $address = new Address();
        $address = $address
            ->withCountryCode('AD')
            ->withLocality("Parr??quia d'Andorra la Vella")
            ->withPostalCode('AD500')
            ->withAddressLine1('C. Prat de la Creu, 62-64');

        // Andorra has no predefined administrative areas, but it does have
        // predefined localities, which must be shown.
        $expectedTextLines = [
            'C. Prat de la Creu, 62-64',
            "AD500 Parr??quia d'Andorra la Vella",
            'Andorra',
        ];
        $this->formatter->setOption('html', false);
        $textAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedTextLines, $textAddress);
    }

    /**
     * @covers \CommerceGuys\Addressing\Formatter\DefaultFormatter
     */
    public function testElSalvadorAddress()
    {
        $address = new Address();
        $address = $address
            ->withCountryCode('SV')
            ->withAdministrativeArea('Ahuachap??n')
            ->withLocality('Ahuachap??n')
            ->withAddressLine1('Some Street 12');

        $expectedHtmlLines = [
            '<p translate="no">',
            '<span class="address-line1">Some Street 12</span><br>',
            '<span class="locality">Ahuachap??n</span><br>',
            '<span class="administrative-area">Ahuachap??n</span><br>',
            '<span class="country">El Salvador</span>',
            '</p>',
        ];
        $htmlAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedHtmlLines, $htmlAddress);

        $expectedTextLines = [
            'Some Street 12',
            'Ahuachap??n',
            'Ahuachap??n',
            'El Salvador',
        ];
        $this->formatter->setOption('html', false);
        $textAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedTextLines, $textAddress);

        $address = $address->withPostalCode('CP 2101');
        $expectedHtmlLines = [
            '<p translate="no">',
            '<span class="address-line1">Some Street 12</span><br>',
            '<span class="postal-code">CP 2101</span>-<span class="locality">Ahuachap??n</span><br>',
            '<span class="administrative-area">Ahuachap??n</span><br>',
            '<span class="country">El Salvador</span>',
            '</p>',
        ];
        $this->formatter->setOption('html', true);
        $htmlAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedHtmlLines, $htmlAddress);

        $expectedTextLines = [
            'Some Street 12',
            'CP 2101-Ahuachap??n',
            'Ahuachap??n',
            'El Salvador',
        ];
        $this->formatter->setOption('html', false);
        $textAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedTextLines, $textAddress);
    }

    /**
     * @covers \CommerceGuys\Addressing\Formatter\DefaultFormatter
     */
    public function testTaiwanAddress()
    {
        // Real addresses in the major-to-minor order would be completely in
        // Traditional Chinese. That's not the case here, for readability.
        $address = new Address();
        $address = $address
            ->withCountryCode('TW')
            ->withAdministrativeArea('Taipei City')
            ->withLocality("Da'an District")
            ->withAddressLine1('Sec. 3 Hsin-yi Rd.')
            ->withPostalCode('106')
            // Any HTML in the fields is supposed to be removed when formatting
            // for text, and escaped when formatting for html.
            ->withOrganization('Giant <h2>Bike</h2> Store')
            ->withGivenName('Te-Chiang')
            ->withFamilyName('Liu')
            ->withLocale('zh-Hant');
        $this->formatter->setLocale('zh-Hant');

        // Test adding a new wrapper attribute, and passing a value as an array.
        $options = ['translate' => 'no', 'class' => ['address', 'postal-address']];
        $this->formatter->setOption('html_attributes', $options);

        $expectedHtmlLines = [
            '<p translate="no" class="address postal-address">',
            '<span class="country">??????</span><br>',
            '<span class="postal-code">106</span><br>',
            '<span class="administrative-area">?????????</span><span class="locality">?????????</span><br>',
            '<span class="address-line1">Sec. 3 Hsin-yi Rd.</span><br>',
            '<span class="organization">Giant &lt;h2&gt;Bike&lt;/h2&gt; Store</span><br>',
            '<span class="family-name">Liu</span> <span class="given-name">Te-Chiang</span>',
            '</p>',
        ];
        $htmlAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedHtmlLines, $htmlAddress);

        $expectedTextLines = [
            '??????',
            '106',
            '??????????????????',
            'Sec. 3 Hsin-yi Rd.',
            'Giant Bike Store',
            'Liu Te-Chiang',
        ];
        $this->formatter->setOption('html', false);
        $textAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedTextLines, $textAddress);
    }

    /**
     * @covers \CommerceGuys\Addressing\Formatter\DefaultFormatter
     */
    public function testUnitedStatesIncompleteAddress()
    {
        // Create a US address without a locality.
        $address = new Address();
        $address = $address
            ->withCountryCode('US')
            ->withAdministrativeArea('CA')
            ->withPostalCode('94043')
            ->withAddressLine1('1098 Alta Ave');

        $expectedHtmlLines = [
            '<p translate="no">',
            '<span class="address-line1">1098 Alta Ave</span><br>',
            '<span class="administrative-area">CA</span> <span class="postal-code">94043</span><br>',
            '<span class="country">United States</span>',
            '</p>',
        ];
        $htmlAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedHtmlLines, $htmlAddress);

        $expectedTextLines = [
            '1098 Alta Ave',
            'CA 94043',
            'United States',
        ];
        $this->formatter->setOption('html', false);
        $textAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedTextLines, $textAddress);

        // Now add the locality, but remove the administrative area.
        $address = $address
            ->withLocality('Mountain View')
            ->withAdministrativeArea('');

        $expectedHtmlLines = [
            '<p translate="no">',
            '<span class="address-line1">1098 Alta Ave</span><br>',
            '<span class="locality">Mountain View</span>, <span class="postal-code">94043</span><br>',
            '<span class="country">United States</span>',
            '</p>',
        ];
        $this->formatter->setOption('html', true);
        $htmlAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedHtmlLines, $htmlAddress);

        $expectedTextLines = [
            '1098 Alta Ave',
            'Mountain View, 94043',
            'United States',
        ];
        $this->formatter->setOption('html', false);
        $textAddress = $this->formatter->format($address);
        $this->assertFormattedAddress($expectedTextLines, $textAddress);
    }

    /**
     * Asserts that the formatted address is valid.
     *
     * @param array  $expectedLines
     * @param string $formattedAddress
     */
    protected function assertFormattedAddress(array $expectedLines, $formattedAddress)
    {
        $expectedLines = implode("\n", $expectedLines);
        $this->assertEquals($expectedLines, $formattedAddress);
    }
}
