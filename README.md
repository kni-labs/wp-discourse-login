# WP Discourse Login

Add Discourse as an SSO Provider for Wordpress

![](https://github.com/kni-labs/wp-discourse-login/workflows/PHP%20Composer/badge.svg)

### Installing

Clone repository

```
git clone https://github.com/kni-labs/wp-discourse-login
```

Install dependencies via [composer](https://getcomposer.org/)

```
composer install
```

## Testing

Code styles and linting testing via [PHP Code Sniffer](https://github.com/squizlabs/PHP_CodeSniffer) are configured for the [--standard=WordPress](https://github.com/WordPress/WordPress-Coding-Standards) rules. 

### Linting

Supply the file(s) to the linter:

```
composer php:lint
```

Autofix is also enabled, and can be ran similarly:

```
composer php:autofix
```

