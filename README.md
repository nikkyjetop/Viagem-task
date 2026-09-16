# Viagem Task

Zadání pro Viagem — mapa katastrálních parcel v okrese Jičín. Po kliknutí na
parcelu se zobrazí její označení, výměra a katastrální reference.

## Požadavky

- PHP 8.1 nebo novější (vyvíjeno a testováno na PHP 8.5.10)
- PHP rozšíření `curl` a `simplexml` (součást standardní instalace PHP)
- Git
- Žádná databáze, žádné závislosti přes Composer — backend je čisté PHP a
  s každým požadavkem komunikuje živě s ČÚZK přes HTTPS.

## Spuštění

```bash
php -S localhost:8000 -t public router.php
```

Poté otevři `http://localhost:8000`.

Proč `router.php`: vestavěný PHP server obsluhuje jen soubory pod svým
docrootem (`public/`), ale `api/` je záměrně mimo `public/` (viz níže).
`router.php` proto přesměruje požadavky na `/api/*` do adresáře `api/`
a všechno ostatní nechá vyřídit server běžným způsobem z `public/`.

## Struktura projektu

```
public/       frontend — soubory obsluhované prohlížečem (HTML, JS, CSS)
api/          API endpoint, který přijímá souřadnice a vrací JSON
src/          doménová logika sdílená z api/
    CuzkClient.php    komunikace s ČÚZK WFS (sestavení a odeslání requestu)
    ParcelParser.php  parsování XML odpovědi z ČÚZK do pole s daty parcely
router.php    router pro vestavěný PHP server (viz výše)
```

`api/parcel.php` je tenký endpoint: ověří vstupní souřadnice, přes
`CuzkClient` získá XML odpověď z ČÚZK, přes `ParcelParser` ji rozparsuje a
následně uplatní vlastní pravidlo aplikace — jestli parcela leží v
podporované oblasti (viz níže). Samotná komunikace s ČÚZK a parsování XML
jsou schválně oddělené v `src/`, aby `api/parcel.php` zůstal čitelný a aby
šlo obě části v budoucnu testovat nebo použít samostatně.

## Jak to funguje

- **Přehled na mapě** — frontend (Leaflet) načte podkladovou vrstvu z
  OpenStreetMap a k ní WMS vrstvu ČÚZK (`services.cuzk.cz/wms/wms.asp`,
  vrstva `KN`), takže hranice a čísla parcel jsou vidět na mapě rovnou, bez
  nutnosti na cokoliv klikat.
- **Detail parcely** — kliknutí na mapu zavolá [`api/parcel.php`](api/parcel.php),
  který přes [`src/CuzkClient.php`](src/CuzkClient.php) zavolá stored query
  `GetFeatureByPoint` na ČÚZK WFS pro feature typu `CadastralParcel`, přes
  [`src/ParcelParser.php`](src/ParcelParser.php) rozparsuje vrácené GML a
  vrátí označení parcely, výměru, národní referenci, katastrální území a
  hranici parcely jako JSON. Frontend podle toho vykreslí polygon a zobrazí
  popisek.

## Pokryté území

Povinné minimum je katastrální území **Jičín**. Aplikace navíc pokrývá tři
sousední katastrální území — **Valdice**, **Holín** a **Železnice** — takže
celé pokryté území tvoří na mapě jeden souvislý celek, ne roztroušené ostrovy.

Rozsah je vynucen dvěma způsoby:
1. `maxBounds` mapy omezují posouvání na ohraničující obdélník kolem těchto
   čtyř území.
2. `api/parcel.php` si z odpovědi ČÚZK WFS přečte katastrální území parcely
   (title odkazu `zoning`) a porovná ho s whitelistem, takže kliknutí těsně
   za hranicí mapy nemůže vrátit data parcely mimo tato čtyři území.

## Zápisník (rozhodnutí, co mě překvapilo, co bych dělala jinak)

- **Vizualizace celého území vs. natahování každé parcely zvlášť.** Místo
  stahování geometrie všech parcel přes WFS a jejich vykreslování na
  klientovi (což by znamenalo stránkovaný dotaz přes bounding box a
  parsování velkého množství GML dopředu) jsem na mapu přidala WMS vrstvu
  přímo od ČÚZK. Je to stejná data, jakou používají mapové prohlížeče ČÚZK,
  vykreslí se okamžitě v jakémkoliv zoomu a mapa tak zůstává plynulá i nad
  celým pokrytým územím. WFS používám jen pro tu jednu parcelu, na kterou
  uživatel klikne — tam potřebuju skutečná atributová data (výměru,
  referenci), která rastrová WMS vrstva neposkytuje.
- **Živé dotazy, žádná lokální databáze.** Každé kliknutí je živý WFS dotaz.
  Lokální kopie parcel celého okresu by potřebovala prostorový index (a
  databázi), což mi pro požadovaný rozsah přišlo jako zbytečná komplikace —
  dotaz na jeden bod se typicky vrátí výrazně pod sekundu.
- **Výběr, která 4 území pokrýt.** Zvolila jsem sousední území Jičína
  (Valdice, Holín, Železnice) záměrně tak, aby pokryté území tvořilo jeden
  souvislý blok, ne čtyři roztroušené body na mapě — díky tomu působí
  omezení `maxBounds` přirozeně, ne svévolně.
- **Překvapení: ČÚZK signalizuje "žádná parcela" bez HTTP chyby.** Kliknutí
  mimo katastr (voda, mezera v katastru, mimo ČR) stále vrátí HTTP 200 s
  prázdným `<FeatureCollection/>`, které nemá namespace `cp`, místo
  obvyklého kořenového elementu `<cp:CadastralParcel>`. `ParcelParser`
  to musí rozpoznat explicitně (`isset($namespaces['cp'])`), nejde se
  spolehnout na HTTP status ani na chybu parsování.
- **Překvapení: WMS vrstva parcel je závislá na měřítku.** Hranice a čísla
  parcel se vykreslí až po přiblížení na určitou úroveň — to je záměrné
  nastavení ČÚZK (`ScaleHint`), ne chyba, takže v zoomu na celé území
  uvidíš podkladovou mapu bez hranic parcel, dokud nepřiblížíš.
- **Co bych řešila s větším časem:** rozšíření pokrytí směrem k celému
  okresu — místo pevně zadaného bounding boxu a whitelistu bych stahovala
  hranici území přímo z WFS správních jednotek ČÚZK; přidala bych pořádný
  loading kurzor/spinner a debounce kliknutí; napsala automatické testy na
  parsování XML v `ParcelParser` (prázdná odpověď, chybějící geometrie,
  vícečásticové parcely); zobrazila by se i samotná hranice katastrálního
  území na mapě; a přidala bych jednoduché cachování odpovědí (parcely se
  nemění často), aby se omezily opakované requesty na ČÚZK při opětovném
  kliknutí do stejné oblasti.
