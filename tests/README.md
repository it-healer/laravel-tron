# Тесты для Laravel Tron

## Запуск тестов

```bash
composer test
```

или

```bash
vendor/bin/phpunit
```

## Запуск с подробным выводом

```bash
vendor/bin/phpunit --testdox
```

## Тесты BIP39

Тесты проверяют функционал генерации и валидации BIP39 мнемоник:

### MnemonicTest

- ✅ **mnemonicGenerate** - Генерация мнемоники (12, 15, 24 слова)
- ✅ **mnemonicValidate** - Валидация корректных и некорректных мнемоник
- ✅ **mnemonicSeed** - Генерация seed из мнемоники
  - Без passphrase
  - С passphrase

### Тестовые данные BIP39

Используются проверенные тестовые векторы BIP39:

**Мнемоника:**
```
still jealous camp wise sense need across scheme smart victory pepper obtain grid tennis wink
```

**Seed (без passphrase):**
```
478b5b98b8cd38711829c92cce8ce4ed0265fc8684e40801810b8873dec19a7d7fd0cdd54fb7bbbfc11c298b5a54f332b8a257819f67917414dff62d33884863
```

**Seed (с passphrase "123456"):**
```
8591a476110f3f5f9ce8918f5d52483f1edbed1b9ed666f9f352a8f073b92462eb7a9cd5d5d5ee71503ae42adad7441ae692316cd34c2500d47b0089cae17ee8
```

## Тесты BIP44 (Адреса Tron)

Тесты проверяют полную цепочку генерации адресов Tron из мнемоники:

### AddressGenerationTest

- ✅ **test_bip44_address_generation_without_passphrase** - Генерация адреса БЕЗ passphrase
- ✅ **test_bip44_address_generation_from_mnemonic** - Генерация адреса С passphrase
- ✅ **test_bip44_address_generation_different_indexes** - Различные индексы генерируют разные адреса
- ✅ **test_private_key_to_public_key** - Конвертация приватного ключа в публичный
- ✅ **test_private_key_to_address** - Конвертация приватного ключа в адрес
- ✅ **test_bip44_derivation_path_consistency** - Консистентность деривации
- ✅ **test_different_mnemonics_produce_different_addresses** - Разные мнемоники → разные адреса
- ✅ **test_passphrase_affects_address_generation** - Passphrase влияет на адрес
- ✅ **test_multiple_addresses_from_same_seed** - Множественная генерация адресов

### Тестовые данные BIP44

Путь деривации для Tron: `m/44'/195'/0'/0`

**БЕЗ passphrase (index=0):**
- Private Key: `e25645eb1e1537f66c50331fd7b39c2b97a6f4d5ae786f46ad3249a0507e06a9`
- Address: `TYrNwi17zVyDb6ZnH12win67qTF24hVo5q`

**С passphrase "123456" (index=0):**
- Private Key: `fa49a96662c43245c84cbad793407008ae1579c1eb4ee400a8667eb5b1dbac7e`
- Address: `TU6DuECpcxCqsgad35nizXuVgzMRBkoKn4`

## Результаты тестов

```
Address Generation (ItHealer\LaravelTron\Tests\Unit\AddressGeneration)
 ✔ Bip44 address generation without passphrase
 ✔ Bip44 address generation from mnemonic
 ✔ Bip44 address generation different indexes
 ✔ Private key to public key
 ✔ Private key to address
 ✔ Bip44 derivation path consistency
 ✔ Different mnemonics produce different addresses
 ✔ Passphrase affects address generation
 ✔ Multiple addresses from same seed

Mnemonic (ItHealer\LaravelTron\Tests\Unit\Mnemonic)
 ✔ Mnemonic generate
 ✔ Mnemonic validate with valid mnemonic
 ✔ Mnemonic validate with invalid mnemonic
 ✔ Mnemonic seed without passphrase
 ✔ Mnemonic seed with passphrase
 ✔ Generated mnemonic is valid
 ✔ Seed generation from generated mnemonic
 ✔ Different passphrases produce different seeds

Tests: 17, Assertions: 77
```
