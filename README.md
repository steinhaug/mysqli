# Mysqli2

Mysqli Abstraction Layer v1.9.0


<div class="show_none">

# Table of Contents

- [Mysqli2](#mysqli2)
- [Table of Contents](#table-of-contents)
- [1. Description](#1-description)
- [2. Version History](#2-version-history)
  - [2.1 Log](#21-log)
    - [v1.9.1](#v191)
    - [v1.9.0](#v190)
    - [V1.7.0](#v170)
    - [v1.6.6](#v166)
    - [v1.6.5](#v165)
    - [v1.6.4](#v164)
    - [v1.6.3](#v163)
    - [v1.6.2](#v162)
- [3. Install by composer](#3-install-by-composer)
- [5. Information](#5-information)
  - [5.1 License](#51-license)
  - [5.2 Author](#52-author)
</div>

# 1. Description

Mysqli2 is an enhanced wrapper around PHP's native MySQLi extension that provides simplified prepared statement execution, better error handling, and development/production mode switching. The class extends mysqli, inheriting all native MySQLi methods while adding streamlined functionality.

**Key Features**  

- **Singleton Pattern**: Single database connection instance
- **Development/Production Modes**: Configurable error reporting
- **Simplified Prepared Statements**: Streamlined syntax for common operations
- **Smart Return Values**: Context-aware return types based on SQL operation
- **Exception Handling**: Optional exception throwing with detailed error information

# 2. Version History

## 2.1 Log

### v1.9.1

    - Updated documentation.
    - Added new methods for better handling of prepared statements and result sets.
    - Improved error handling and logging.

### v1.9.0

    - Ny refaktorert klasse, nye metoder. Se docs/mysqli2_documentation-v1.9.md

### V1.7.0

    - Breaking file into smaller files, better readability.

### v1.6.6

    - Updated readme.

### v1.6.5

    - Bugfix, error_number has to be int

### v1.6.4

    - buddy() updated, has prepared output aswell. echo $mysqli->buddy('table','insert','prepared');
    - parse_col_type, added prepared for type

### v1.6.3

    - Added mode for ->result('assoc') without using second parameter.

### v1.6.2

    - Updated for PHP 8.1  

# 3. Install by composer

To install the old deprecated library use composer:

    composer require "steinhaug/mysqli":"~1.6."

    // documentation:
    docs/mysqli2-documentation-v1.6.md

To install the library use composer:

    composer require "steinhaug/mysqli":"^1.9.0"

    // documentation:
    docs/mysqli2_documentation-v1.9.md

Dump autoloaders:  

    composer dump-autoload --optimize

# 5. Information

## 5.1 License

This project is licensed under the terms of the  [MIT](http://www.opensource.org/licenses/mit-license.php) License. Enjoy!

## 5.2 Author

Kim Steinhaug, steinhaug at gmail dot com.

**Sosiale lenker:**
[LinkedIn](https://www.linkedin.com/in/steinhaug/), [SoundCloud](https://soundcloud.com/steinhaug), [Instagram](https://www.instagram.com/steinhaug), [Youtube](https://www.youtube.com/@kimsteinhaug), [X](https://x.com/steinhaug), [Ko-Fi](https://ko-fi.com/steinhaug), [Github](https://github.com/steinhaug), [Gitlab](https://gitlab.com/steinhaug)

**Generative AI lenker:**
[Udio](https://www.udio.com/creators/Steinhaug), [Suno](https://suno.com/@steinhaug), [Huggingface](https://huggingface.co/steinhaug)

**Resurser og hjelpesider:**
[Linktr.ee/steinhaugai](https://linktr.ee/steinhaugai), [Linktr.ee/stainhaug](https://linktr.ee/stainhaug), [pinterest/steinhaug](https://no.pinterest.com/steinhaug/), [pinterest/stainhaug](https://no.pinterest.com/stainhaug/)
