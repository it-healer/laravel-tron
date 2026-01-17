<?php

namespace ItHealer\LaravelTron\Tests\Unit;

use ItHealer\LaravelTron\Concerns\Mnemonic;
use PHPUnit\Framework\TestCase;

class MnemonicTest extends TestCase
{
    use Mnemonic;

    /**
     * Test mnemonic generation
     */
    public function test_mnemonic_generate(): void
    {
        // Test default word count (15)
        $mnemonic = $this->mnemonicGenerate();
        $this->assertIsArray($mnemonic);
        $this->assertCount(15, $mnemonic);

        // Test each word is a string
        foreach ($mnemonic as $word) {
            $this->assertIsString($word);
            $this->assertNotEmpty($word);
        }

        // Test custom word count (12)
        $mnemonic12 = $this->mnemonicGenerate(12);
        $this->assertCount(12, $mnemonic12);

        // Test custom word count (24)
        $mnemonic24 = $this->mnemonicGenerate(24);
        $this->assertCount(24, $mnemonic24);
    }

    /**
     * Test mnemonic validation with valid mnemonic
     */
    public function test_mnemonic_validate_with_valid_mnemonic(): void
    {
        $validMnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";

        // Test with string
        $this->assertTrue($this->mnemonicValidate($validMnemonic));

        // Test with array
        $mnemonicArray = explode(' ', $validMnemonic);
        $this->assertTrue($this->mnemonicValidate($mnemonicArray));
    }

    /**
     * Test mnemonic validation with invalid mnemonic
     */
    public function test_mnemonic_validate_with_invalid_mnemonic(): void
    {
        $invalidMnemonic = "invalid words that are not in wordlist";
        $this->assertFalse($this->mnemonicValidate($invalidMnemonic));

        // Test with invalid checksum (changed last word to break checksum)
        $invalidChecksum = "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon";
        $this->assertFalse($this->mnemonicValidate($invalidChecksum));

        // Test with wrong number of words
        $wrongWordCount = "abandon abandon abandon abandon";
        $this->assertFalse($this->mnemonicValidate($wrongWordCount));
    }

    /**
     * Test seed generation without passphrase
     */
    public function test_mnemonic_seed_without_passphrase(): void
    {
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";
        $expectedSeed = "478b5b98b8cd38711829c92cce8ce4ed0265fc8684e40801810b8873dec19a7d7fd0cdd54fb7bbbfc11c298b5a54f332b8a257819f67917414dff62d33884863";

        // Test with string
        $seed = $this->mnemonicSeed($mnemonic);
        $this->assertEquals($expectedSeed, $seed);

        // Test with array
        $mnemonicArray = explode(' ', $mnemonic);
        $seed = $this->mnemonicSeed($mnemonicArray);
        $this->assertEquals($expectedSeed, $seed);

        // Test with explicit null passphrase
        $seed = $this->mnemonicSeed($mnemonic, null);
        $this->assertEquals($expectedSeed, $seed);

        // Test with empty string passphrase (should be same as null)
        $seed = $this->mnemonicSeed($mnemonic, '');
        $this->assertEquals($expectedSeed, $seed);
    }

    /**
     * Test seed generation with passphrase
     */
    public function test_mnemonic_seed_with_passphrase(): void
    {
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";
        $passphrase = "123456";
        $expectedSeed = "8591a476110f3f5f9ce8918f5d52483f1edbed1b9ed666f9f352a8f073b92462eb7a9cd5d5d5ee71503ae42adad7441ae692316cd34c2500d47b0089cae17ee8";

        // Test with string mnemonic
        $seed = $this->mnemonicSeed($mnemonic, $passphrase);
        $this->assertEquals($expectedSeed, $seed);

        // Test with array mnemonic
        $mnemonicArray = explode(' ', $mnemonic);
        $seed = $this->mnemonicSeed($mnemonicArray, $passphrase);
        $this->assertEquals($expectedSeed, $seed);
    }

    /**
     * Test that generated mnemonics are valid
     */
    public function test_generated_mnemonic_is_valid(): void
    {
        $mnemonic = $this->mnemonicGenerate(12);
        $this->assertTrue($this->mnemonicValidate($mnemonic));

        $mnemonic15 = $this->mnemonicGenerate(15);
        $this->assertTrue($this->mnemonicValidate($mnemonic15));

        $mnemonic24 = $this->mnemonicGenerate(24);
        $this->assertTrue($this->mnemonicValidate($mnemonic24));
    }

    /**
     * Test seed generation from generated mnemonic
     */
    public function test_seed_generation_from_generated_mnemonic(): void
    {
        $mnemonic = $this->mnemonicGenerate(12);

        // Should be able to generate seed
        $seed = $this->mnemonicSeed($mnemonic);
        $this->assertIsString($seed);
        $this->assertEquals(128, strlen($seed)); // 64 bytes * 2 (hex)

        // Seed should be consistent
        $seed2 = $this->mnemonicSeed($mnemonic);
        $this->assertEquals($seed, $seed2);

        // Seed with passphrase should be different
        $seedWithPassphrase = $this->mnemonicSeed($mnemonic, 'test');
        $this->assertNotEquals($seed, $seedWithPassphrase);
    }

    /**
     * Test different passphrases produce different seeds
     */
    public function test_different_passphrases_produce_different_seeds(): void
    {
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";

        $seed1 = $this->mnemonicSeed($mnemonic);
        $seed2 = $this->mnemonicSeed($mnemonic, "pass1");
        $seed3 = $this->mnemonicSeed($mnemonic, "pass2");

        $this->assertNotEquals($seed1, $seed2);
        $this->assertNotEquals($seed1, $seed3);
        $this->assertNotEquals($seed2, $seed3);
    }
}
