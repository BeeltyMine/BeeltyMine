# BeeltyMine Dashboard Analysis

Generated: 2026-03-29 12:03:56

## Last Test Results

- Custom: Failed (exit=1, duration=0.61s)
- PHPStan: Failed (exit=1, duration=0.56s)
- PHPUnit: Failed (exit=1, duration=0.28s)
- PHPStan: Failed (exit=1, duration=0.71s)
- PHPUnit: Failed (exit=1, duration=0.61s)

## TODO Snapshot

# BeeltyMine Performance TODO

Bu liste, kod tabani okunarak cikarilmis performans iyilestirme adaylarini onceliklendirir.
Odak noktasi, ana thread spike'larini dusurmek, chunk/network maliyetini azaltmak ve autosave kaynakli TPS kaybini sinirlamaktir.

## Kritik

### 1. Login sirasindaki player data yuklemesini async hale getir
Kisa aciklama: Oyuncu girisinde `.dat` dosyasi senkron okunuyor, unzip ediliyor ve NBT parse ediliyor; bu da login spike uretiyor.

Uzun aciklama: `src/network/mcpe/NetworkSession.php` icinde login akisinda `getOfflinePlayerData()` dogrudan ana thread uzerinde cagriliyor. Bu cagrinin altinda `src/player/DatFilePlayerDataProvider.php` tarafinda dosya okuma, `zlib_decode()` ve `BigEndianNbtSerializer()->read()` var. Oyuncu girisi artis gosterdiginde veya disk yavasladiginda bu maliyet TPS'i dogrudan etkiler. Oyuncu verisini async worker'da okuyup parse etmek, sonucu login tamamlanmadan once promise/callback ile ana threade almak ve sadece gerekli minimum veriyi sync tarafta islemek daha stabil olur.

Ilgili kod: `src/network/mcpe/NetworkSession.php`, `src/Server.php`, `src/player/DatFilePlayerDataProvider.php`

### 2. Autosave akisini batch ve dirty-state mantigiyla yeniden duzenle
Kisa aciklama: Autosave tek tick icinde tum dunyalari ve spawn olmus oyunculari senkron kaydediyor; bu durum periyodik lag spike uretir.

Uzun aciklama: `src/world/WorldManager.php` icindeki `doAutoSave()` her autosave dongusunde once oyunculari `save()` ediyor, sonra `world->save(false)` cagiriyor. Oyuncu save zinciri `src/player/Player.php` -> `src/Server.php` -> `src/player/DatFilePlayerDataProvider.php` yolundan disk yazimina gidiyor. World save tarafinda ise ayni tick icinde tum chunk save operasyonlari devreye giriyor. Bunu tek seferde yapmak yerine dirty oyunculari ve dirty dunyalari kuyruga alip birden fazla tick'e yaymak, disk flush'larini batch yapmak ve "oyuncu degismediyse yazma" mantigi eklemek TPS acisindan cok daha guvenli olur.

Ilgili kod: `src/world/WorldManager.php`, `src/player/Player.php`, `src/Server.php`, `src/player/DatFilePlayerDataProvider.php`

### 3. Chunk save tarafinda temiz chunk'lari tamamen atla
Kisa aciklama: Save sirasinda sadece dirty flag'e bakmak yetmiyor; temiz chunk'lar icin bile entity/tile serialization ve provider save cagrisi yapiliyor.

Uzun aciklama: `src/world/World.php` icindeki `saveChunks()` tum yuklu chunk'lari dolasiyor ve her biri icin `ChunkData` uretiyor. Bu asamada `array_filter()`, `array_values()`, `array_map()` ile entity ve tile NBT'leri yeniden olusturuluyor. Ardindan provider `saveChunk()` cagriliyor. `src/world/format/io/leveldb/LevelDB.php` ve `src/world/format/io/region/WritableRegionWorldProvider.php` tarafinda da bu cagrilar hala yazma maliyetine donusebiliyor. Gercekten dirty olmayan chunk'lari en ust seviyede skip etmek, entity/tile icin ayri dirty takibi tutmak ve "write only changed sections" mantigina gecmek autosave maliyetini ciddi dusurur.

Ilgili kod: `src/world/World.php`, `src/world/format/io/leveldb/LevelDB.php`, `src/world/format/io/region/WritableRegionWorldProvider.php`

### 4. World tick icindeki block update kuyruklarina tick-basina butce koy
Kisa aciklama: Bazi kuyruklar bosalana kadar isleniyor; redstone, farm veya zincir reaksiyonlar tek tick'i yutabiliyor.

Uzun aciklama: `src/world/World.php` icindeki `actuallyDoTick()` fonksiyonunda scheduled block update ve neighbour block update kuyruklari `while(queue > 0)` mantigiyla sonuna kadar bosaltiliyor. Yogun block degisimi oldugunda bu kuyruklar bir tick icinde kontrolsuz buyuyebilir ve server geri kalan islere zaman ayiramaz. Tick-basina islem butcesi, maksimum islenecek update sayisi ve backlog metrigi eklemek daha tahmin edilebilir frame time verir. Gerekirse kalan update'leri sonraki tick'e devretmek TPS'i daha iyi korur.

Ilgili kod: `src/world/World.php`

## Yuksek

### 5. Chunk gonderim zincirinde daha agresif batching ve flush kontrolu yap
Kisa aciklama: Kucuk paketler ve sik flush davranisi gereksiz encode/compress maliyeti uretebilir.

Uzun aciklama: `src/player/Player.php` tarafinda chunk istekleri kademe kademe gidiyor; `src/network/mcpe/NetworkSession.php` tarafinda her tick `flushGamePacketQueue()` calisiyor; `src/world/World.php` ise chunk bazli packet buffer biriktirip yayina cikiyor. Bu zincir, cok oyunculu ve hizli hareketli senaryolarda kucuk batch sayisini arttirabilir. Daha iyi bir flush esigi, ayni hedefe giden paketleri daha uzun bir pencerede birlestirme ve gereksiz mini-batch olusumunu azaltma yaklasimi CPU ve bant genisligi kullanimini iyilestirir.

Ilgili kod: `src/player/Player.php`, `src/network/mcpe/NetworkSession.php`, `src/world/World.php`, `src/network/mcpe/StandardPacketBroadcaster.php`

### 6. Chunk cache invalidation mekanizmasina debounce/coalesce ekle
Kisa aciklama: Sik block/chunk degisimi olan alanlarda cache surekli bozulup yeniden async hazirlaniyor; worker havuzu gereksiz yoruluyor.

Uzun aciklama: `src/network/mcpe/cache/ChunkCache.php` icinde chunk degisince cache dusuruluyor veya yeniden baslatiliyor. `onBlockChanged()` tarafinda da cache direkt siliniyor. Yapi editleri, hopper alanlari veya farm'larda ayni chunk kisa surede birden cok kez invalidate olursa, arka planda ayni chunk icin tekrar tekrar hazirlama/compression maliyeti olusabilir. Kisa sureli debounce, per-chunk "dirty generation id" veya tick-sonu yeniden hazirlama stratejisi worker churn'u ve gereksiz compression isini azaltir.

Ilgili kod: `src/network/mcpe/cache/ChunkCache.php`, `src/network/mcpe/ChunkRequestTask.php`

### 7. Block ve collision cache temizligini tumden tarama yerine artimsal hale getir
Kisa aciklama: Cache cap kucuk ve temizlik mantigi tarama/flush agirlikli; sicak bolgelerde cache verimi kolayca kayboluyor.

Uzun aciklama: `src/world/World.php` icinde `BLOCK_CACHE_SIZE_CAP` sadece `2048` ve `clearCache()` cagrisi cache'i sayarak tarayip esik asilirsa komple bosaltabiliyor. `src/Server.php` de bu temizligi periyodik olarak tum dunyalarda cagiriyor. Bu yaklasim hem tarama maliyeti getiriyor hem de sicak cache verisini cabuk yok ediyor. Daha buyuk ama kontrollu bir cap, dogrudan boyut sayaci, LRU benzeri artimsal trim ve collision cache icin ayri limit stratejisi daha dengeli performans verir.

Ilgili kod: `src/world/World.php`, `src/Server.php`

## Orta

