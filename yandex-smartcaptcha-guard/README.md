# Yandex SmartCaptcha Guard

Yandex SmartCaptcha Guard — легковесный WordPress-плагин для защиты кастомных AJAX-форм с помощью Yandex SmartCaptcha.

Плагин интегрируется с пользовательскими mailer-хуками, проверяет капчу перед отправкой формы, показывает ошибки прямо под капчей и автоматически удаляет captcha-токены из писем.

## GitHub

Repository:
https://github.com/digateagency/WP-plugins/tree/main/yandex-smartcaptcha-guard

## Скачать

[Скачать последнюю версию](https://github.com/digateagency/WP-plugins/releases/download/v3.8/Yandex.SmartCaptcha.Guard.v3.8.zip)

## Возможности

- интеграция с Yandex SmartCaptcha
- защита кастомных AJAX-форм
- inline-ошибки под капчей
- поддержка браузерной валидации
- без зависимости от ACF
- автоматическое удаление captcha-токенов из писем
- PHP helper + shortcode

---

# Быстрый старт

1. Установите и активируйте плагин.

2. Откройте:

```txt
Настройки → Yandex SmartCaptcha
```

3. Укажите:
- Client Key
- Server Key

4. Вставьте капчу в форму.

## PHP helper

```php
<?php the_yandex_smartcaptcha(); ?>
```

## Shortcode

```txt
[yandex_smartcaptcha]
```

5. Готово.

---

## Логика работы

### Если капча не настроена

Плагин покажет сообщение:

```txt
Капча не настроена.
```

### Если капча не пройдена

- отправка формы блокируется
- под капчей показывается сообщение об ошибке

### Если капча пройдена

- форма отправляется
- captcha token автоматически удаляется из письма
