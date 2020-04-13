# VariableToJson

Convert any variable or object to json.

### Installation

`composer require alexkratky/variable-to-json`

### Usage

```php
require 'vendor/autoload.php';

use AlexKratky\VariableToJson;

$x = new TestClass(); // test class
$x->load();
$x->load2();
$x->load3();

file_put_contents("output.json", VariableToJson::convert($x, true));

file_put_contents("output2.json", VariableToJson::convert([
    'test' => 1
], true));

file_put_contents("output3.json", VariableToJson::convert(function(string $text ="xx") {
    echo $text;
}, true));
```

### Custom options
* `setDepth($maxDepth = 5)` - Set maximum depth of object's scan
* `setLength($maxLength = 300)` - Set maximum string length