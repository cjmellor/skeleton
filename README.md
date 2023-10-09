# 💀 Package Skeleton CLI

![Social Cover](https://github.com/cjmellor/skeleton/assets/1848476/733b39c1-c202-44be-96fa-fc1f3b70481b)

The Package Skeleton CLI is a tool to generate a package skeleton to start building a Laravel package.

## Installation

It is recommended to click the **Use this template** button and choose to create a new repository

![Social Cover](https://github.com/cjmellor/skeleton/assets/1848476/ba82d059-9989-43c2-a1a2-d0970c576809)

Otherwise, you can clone this repository into a new folder and launch the install:

```bash
git clone https://github.com/cjmellor/skeleton my-package

cd my-package

composer install
```

If you cloned the repo, you will need to remove the `origin` remote and add a new one

```bash
git remote remove origin

git remote add origin git@github.com:<username>/<package-name>.git
```

Replace `<username` and `<package-name>` where applicable.

## Usage

Run the following command to generate a package skeleton:

```bash
php build
```

You will be prompted with multiple questions in relation to your package. Once you have answered all the questions, the package skeleton will be generated.

## License

The Package Skeleton CLI is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
