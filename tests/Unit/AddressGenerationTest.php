<?php

namespace ItHealer\LaravelTron\Tests\Unit;

use BIP\BIP44;
use ItHealer\LaravelTron\Api\Helpers\AddressHelper;
use ItHealer\LaravelTron\Concerns\Mnemonic;
use ItHealer\LaravelTron\Support\Key;
use PHPUnit\Framework\TestCase;

class AddressGenerationTest extends TestCase
{
    use Mnemonic;

    /**
     * Test complete BIP44 address generation from mnemonic WITHOUT passphrase
     */
    public function test_bip44_address_generation_without_passphrase(): void
    {
        // Test mnemonic
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";

        // Generate seed from mnemonic WITHOUT passphrase
        $seed = $this->mnemonicSeed($mnemonic);

        // Expected values for index 0 (WITHOUT passphrase)
        $expectedPrivateKey = "e25645eb1e1537f66c50331fd7b39c2b97a6f4d5ae786f46ad3249a0507e06a9";
        $expectedAddress = "TYrNwi17zVyDb6ZnH12win67qTF24hVo5q";

        // Derive key using BIP44 path for Tron (m/44'/195'/0'/0)
        $hdKey = BIP44::fromMasterSeed($seed)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);

        $privateKey = (string)$hdKey->privateKey;

        // Test private key matches expected
        $this->assertEquals($expectedPrivateKey, $privateKey);

        // Generate address from private key
        $addressString = AddressHelper::toBase58('41' . Key::privateKeyToAddress($privateKey));

        // Test address matches expected
        $this->assertEquals($expectedAddress, $addressString);
    }

    /**
     * Test complete BIP44 address generation from mnemonic WITH passphrase
     */
    public function test_bip44_address_generation_from_mnemonic(): void
    {
        // Test mnemonic
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";
        $passphrase = "123456";

        // Generate seed from mnemonic WITH passphrase
        $seed = $this->mnemonicSeed($mnemonic, $passphrase);

        // Expected values for index 0 (WITH passphrase "123456")
        $expectedPrivateKey = "fa49a96662c43245c84cbad793407008ae1579c1eb4ee400a8667eb5b1dbac7e";
        $expectedAddress = "TU6DuECpcxCqsgad35nizXuVgzMRBkoKn4";

        // Derive key using BIP44 path for Tron (m/44'/195'/0'/0)
        $hdKey = BIP44::fromMasterSeed($seed)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);

        $privateKey = (string)$hdKey->privateKey;

        // Test private key matches expected
        $this->assertEquals($expectedPrivateKey, $privateKey);

        // Generate address from private key
        $addressString = AddressHelper::toBase58('41' . Key::privateKeyToAddress($privateKey));

        // Test address matches expected
        $this->assertEquals($expectedAddress, $addressString);
    }

    /**
     * Test BIP44 address generation with different indexes
     */
    public function test_bip44_address_generation_different_indexes(): void
    {
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";
        $seed = $this->mnemonicSeed($mnemonic);

        // Test index 0
        $hdKey0 = BIP44::fromMasterSeed($seed)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);
        $privateKey0 = (string)$hdKey0->privateKey;
        $address0 = AddressHelper::toBase58('41' . Key::privateKeyToAddress($privateKey0));

        // Test index 1
        $hdKey1 = BIP44::fromMasterSeed($seed)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(1);
        $privateKey1 = (string)$hdKey1->privateKey;
        $address1 = AddressHelper::toBase58('41' . Key::privateKeyToAddress($privateKey1));

        // Different indexes should produce different keys and addresses
        $this->assertNotEquals($privateKey0, $privateKey1);
        $this->assertNotEquals($address0, $address1);

        // Each private key should be 64 hex characters
        $this->assertEquals(64, strlen($privateKey0));
        $this->assertEquals(64, strlen($privateKey1));

        // Each address should start with 'T'
        $this->assertStringStartsWith('T', $address0);
        $this->assertStringStartsWith('T', $address1);
    }

    /**
     * Test private key to public key conversion
     */
    public function test_private_key_to_public_key(): void
    {
        $privateKey = "fa49a96662c43245c84cbad793407008ae1579c1eb4ee400a8667eb5b1dbac7e";

        $publicKey = Key::privateKeyToPublicKey($privateKey);

        // Public key should be 130 characters (65 bytes * 2 for hex)
        $this->assertEquals(130, strlen($publicKey));

        // Public key should start with '04' (uncompressed)
        $this->assertStringStartsWith('04', $publicKey);
    }

    /**
     * Test private key to address conversion
     */
    public function test_private_key_to_address(): void
    {
        $privateKey = "fa49a96662c43245c84cbad793407008ae1579c1eb4ee400a8667eb5b1dbac7e";
        $expectedAddress = "TU6DuECpcxCqsgad35nizXuVgzMRBkoKn4";

        // Generate address from private key
        $addressHex = Key::privateKeyToAddress($privateKey);
        $addressBase58 = AddressHelper::toBase58('41' . $addressHex);

        $this->assertEquals($expectedAddress, $addressBase58);
    }

    /**
     * Test BIP44 derivation path consistency
     */
    public function test_bip44_derivation_path_consistency(): void
    {
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";
        $seed = $this->mnemonicSeed($mnemonic);

        // Derive the same key multiple times
        $hdKey1 = BIP44::fromMasterSeed($seed)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);

        $hdKey2 = BIP44::fromMasterSeed($seed)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);

        // Should produce the same private key
        $this->assertEquals((string)$hdKey1->privateKey, (string)$hdKey2->privateKey);
    }

    /**
     * Test that different mnemonics produce different addresses
     */
    public function test_different_mnemonics_produce_different_addresses(): void
    {
        $mnemonic1 = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";
        $seed1 = $this->mnemonicSeed($mnemonic1);

        // Generate a different mnemonic
        $mnemonic2 = $this->mnemonicGenerate(15);
        $seed2 = $this->mnemonicSeed($mnemonic2);

        // Derive addresses from both seeds
        $hdKey1 = BIP44::fromMasterSeed($seed1)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);
        $address1 = AddressHelper::toBase58('41' . Key::privateKeyToAddress((string)$hdKey1->privateKey));

        $hdKey2 = BIP44::fromMasterSeed($seed2)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);
        $address2 = AddressHelper::toBase58('41' . Key::privateKeyToAddress((string)$hdKey2->privateKey));

        // Different mnemonics should produce different addresses
        $this->assertNotEquals($address1, $address2);
    }

    /**
     * Test passphrase affects address generation
     */
    public function test_passphrase_affects_address_generation(): void
    {
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";

        // Generate seeds with and without passphrase
        $seedNoPassphrase = $this->mnemonicSeed($mnemonic);
        $seedWithPassphrase = $this->mnemonicSeed($mnemonic, "123456");

        // Derive addresses from both seeds
        $hdKey1 = BIP44::fromMasterSeed($seedNoPassphrase)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);
        $address1 = AddressHelper::toBase58('41' . Key::privateKeyToAddress((string)$hdKey1->privateKey));

        $hdKey2 = BIP44::fromMasterSeed($seedWithPassphrase)
            ->derive("m/44'/195'/0'/0")
            ->deriveChild(0);
        $address2 = AddressHelper::toBase58('41' . Key::privateKeyToAddress((string)$hdKey2->privateKey));

        // Different passphrases should produce different addresses
        $this->assertNotEquals($address1, $address2);

        // Address WITHOUT passphrase
        $this->assertEquals("TYrNwi17zVyDb6ZnH12win67qTF24hVo5q", $address1);

        // Address WITH passphrase "123456" should be the expected one
        $this->assertEquals("TU6DuECpcxCqsgad35nizXuVgzMRBkoKn4", $address2);
    }

    /**
     * Test multiple addresses can be derived from the same seed
     */
    public function test_multiple_addresses_from_same_seed(): void
    {
        $mnemonic = "still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink";
        $seed = $this->mnemonicSeed($mnemonic, "123456"); // WITH passphrase

        $addresses = [];
        $privateKeys = [];

        // Generate 5 addresses from the same seed
        for ($i = 0; $i < 5; $i++) {
            $hdKey = BIP44::fromMasterSeed($seed)
                ->derive("m/44'/195'/0'/0")
                ->deriveChild($i);

            $privateKey = (string)$hdKey->privateKey;
            $address = AddressHelper::toBase58('41' . Key::privateKeyToAddress($privateKey));

            $addresses[] = $address;
            $privateKeys[] = $privateKey;
        }

        // All addresses should be unique
        $this->assertCount(5, array_unique($addresses));
        $this->assertCount(5, array_unique($privateKeys));

        // First address should match expected (WITH passphrase "123456")
        $this->assertEquals("TU6DuECpcxCqsgad35nizXuVgzMRBkoKn4", $addresses[0]);
        $this->assertEquals("fa49a96662c43245c84cbad793407008ae1579c1eb4ee400a8667eb5b1dbac7e", $privateKeys[0]);
    }
}
