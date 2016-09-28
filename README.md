# stripe-php-tls-compat

Stripe recently began deprecating older TLS versions, and it will soon be the case that only TLS 1.2 will be allowed to communicate with Stripe's API. You can read more about this change on [Stripe's blog](https://stripe.com/blog/upgrading-tls).

In order to maximize compatibility of our PHP library, we tried a few different ways of controlling which TLS version is used. As a result, not all versions exhibit the same behavior.

This self-contained PHP tool will gather relevant information about your server's environment and software versions and tell you which versions of the [Stripe PHP library](https://github.com/stripe/stripe-php#stripe-php-bindings) can be used on your server.

## Usage

1. Download `stripe-php-tls-compat.php` and place it somewhere on your web server.

2. View the file from your web browser.

3. That's it! When run, the script will gather relevant information, run some tests, and output a table listing your server's compatibility with each version of Stripe's PHP library, as well as a recommendation for which the version you should use. (Hint: you should use the latest version ;) )
