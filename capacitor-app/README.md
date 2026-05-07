# ЭРА ЭТП — Android-обёртка (Capacitor)

Тонкая нативная оболочка вокруг сайта https://forsage.ct.ws.
Внутри запускается WebView, который показывает живой сайт.
Никаких отдельных деплоев — что есть на сайте, то и в приложении.

## Как пересобрать .apk

Требуется JDK 17 и Android SDK с `platforms;android-34` и `build-tools;34.0.0`.

```bash
cd capacitor-app
npm install
npx cap sync android
cd android
./gradlew assembleDebug
```

APK выйдет в `android/app/build/outputs/apk/debug/app-debug.apk`.

## Как поменять адрес сайта

`capacitor.config.json` → `server.url`. Пересобрать.

## Как залить в Google Play

Нужен Release-build с подписью. Команда:
```bash
cd android && ./gradlew assembleRelease
```
Заранее положить keystore и прописать в `android/app/build.gradle` блок `signingConfigs`.

## iOS

Не сгенерирован — нужен macOS + Xcode + Apple Developer Account.
На любой macOS-машине:
```bash
npx cap add ios && npx cap open ios
```
