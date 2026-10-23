# Strona mechanika – motyw WordPress

Gotowa strona dla warsztatu samochodowego (Warszawa Mokotów, ul. Bonifacego 10) z **kalendarzem rezerwacji wizyt** i **lokalnym SEO**. Projekt testowy NextPage.

## Co jest w środku

- strona główna, Usługi + 6 podstron usług, Umów wizytę, Kontakt (mapa), Polityka prywatności,
- rezerwacje online: usługa → dzień → wolna godzina; zajęte terminy i święta są blokowane,
- zakładka **Wizyty** w panelu WordPressa (lista, statusy, maile do klienta),
- SEO: tytuły, opisy, dane strukturalne Google, mapa witryny,
- wersja na telefon z przyciskami „Zadzwoń” i „Umów wizytę”.

Wymagania: WordPress 6.0+, PHP 7.4+.

---

## Najszybciej: podgląd w przeglądarce (bez instalacji)

1. Pobierz ten projekt: zielony przycisk **Code → Download ZIP**. **Nie rozpakowuj go.**
2. Wejdź na **https://playground.wordpress.net** – w przeglądarce uruchomi się darmowy WordPress.
3. Kliknij przycisk WordPressa w lewym górnym rogu (albo dopisz `/wp-admin` do adresu), żeby przejść do panelu.
4. **Wygląd → Motywy → Dodaj nowy motyw → Wyślij motyw na serwer** → wybierz pobrany ZIP → **Zainstaluj teraz** → **Włącz**.
5. Otwórz stronę główną – gotowe.

> Playground działa tylko w Twojej przeglądarce. Po zamknięciu karty wszystko znika, więc to tylko podgląd.

## Na prawdziwym hostingu

1. Zainstaluj WordPressa na hostingu (np. przez instalator „Softaculous” w panelu hostingu), język **polski**.
2. Pobierz ZIP z GitHuba (**Code → Download ZIP**, nie rozpakowuj).
3. W panelu WordPressa: **Wygląd → Motywy → Dodaj nowy motyw → Wyślij motyw na serwer** → ZIP → **Zainstaluj** → **Włącz**.
4. **Ustawienia → Bezpośrednie odnośniki → Zapisz zmiany** (odświeża adresy podstron).

Po włączeniu motyw sam tworzy strony, menu i ustawia stronę główną.

## Ustawienia (bez programowania)

**Wygląd → Dostosuj → Warsztat – ustawienia:**

| Sekcja | Co ustawić |
| --- | --- |
| Dane firmy | nazwa, telefon, e-mail, adres, kod pocztowy, współrzędne mapy, zdjęcie w tle |
| Godziny otwarcia | np. `08:00-17:00`, puste pole = nieczynne |
| Rezerwacje | e-mail do powiadomień, długość terminu, ile aut naraz, urlopy |
| Google i social | link do wizytówki Google, Facebook |
| FAQ | pytania w formacie `Pytanie \| Odpowiedź` |

Teksty podstron zmienia się w **Strony**.

## Test rezerwacji

1. Wejdź na stronę **Umów wizytę**, wybierz usługę, dzień, godzinę, wpisz dane i wyślij.
2. W panelu pojawi się wpis w zakładce **Wizyty**.

Żeby maile o rezerwacjach dochodziły, zainstaluj wtyczkę **WP Mail SMTP** i podłącz skrzynkę pocztową (na darmowych hostingach wysyłka maili bywa zablokowana – rezerwacje i tak zapisują się w panelu).

## Struktura plików

```
style.css            wygląd strony
functions.php        start motywu
front-page.php       strona główna
page.php             podstrony
header.php/footer.php  nagłówek i stopka
inc/booking.php      system rezerwacji
inc/seo.php          SEO i dane strukturalne
inc/customizer.php   panel ustawień
inc/setup-content.php  treści tworzone przy instalacji
assets/booking.js    kalendarz rezerwacji
assets/main.js       menu mobilne, mapa
```
