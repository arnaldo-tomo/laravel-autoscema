# Laravel AutoSchema

🚀 **Automatically generate TypeScript types and validation schemas from Laravel Models with zero configuration.**

[![Latest Version](https://img.shields.io/packagist/v/arnaldo-tomo/laravel-autoscema.svg)](https://packagist.org/packages/arnaldo-tomo/laravel-autoscema)
[![PHP Version](https://img.shields.io/packagist/php-v/arnaldo-tomo/laravel-autoscema.svg)](https://packagist.org/packages/arnaldo-tomo/laravel-autoscema)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x-red.svg)](https://packagist.org/packages/arnaldo-tomo/laravel-autoscema)
[![License](https://img.shields.io/packagist/l/arnaldo-tomo/laravel-autoscema.svg)](https://packagist.org/packages/arnaldo-tomo/laravel-autoscema)

## ✨ Features

- **🔄 Zero Configuration** - Works out of the box with intelligent defaults
- **📝 TypeScript Types** - Generate perfect TypeScript interfaces from Eloquent models
- **🔗 Relationships** - Automatically handle Eloquent relationships
- **✅ Validation Schemas** - Generate Zod/Yup schemas from Form Requests
- **🌐 API Client** - Generate typed API clients for your endpoints
- **👁️ File Watcher** - Real-time regeneration when models change
- **🎯 Framework Integration** - Perfect for Inertia.js, React, Vue, and more

## 🚀 Quick Start

### Installation

```bash
composer require arnaldo-tomo/laravel-autoscema
```

### Initialize

```bash
php artisan schema:init
```

### Generate Types

```bash
php artisan schema:generate
```

That's it! Your TypeScript types are ready in `resources/js/types/`.

## 📋 Example

### Laravel Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'age'];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'preferences' => 'json',
    ];
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

### Generated TypeScript

```typescript
// Generated at 2025-01-20 10:30:00 by Laravel AutoSchema

export interface User {
  id: number;
  name: string;
  email: string;
  age: number;
  preferences: Record<string, any> | null;
  email_verified_at: Date | null;
  created_at: Date;
  updated_at: Date;
  
  // Relationships
  posts?: Post[];
}

export type UserType = User;
```

### Form Request Integration

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'age' => 'nullable|integer|min:18',
        ];
    }
}
```

### Generated Validation Schema

```typescript
// Generated Zod schema
export const createUserSchema = z.object({
  name: z.string().max(255),
  email: z.string().email(),
  age: z.number().min(18).nullable(),
});

export type CreateUserInput = z.infer<typeof createUserSchema>;
```

## 🛠️ Commands

### Generate Types

```bash
# Generate all types
php artisan schema:generate

# Generate for specific models
php artisan schema:generate --model=User --model=Post

# Preview without writing files
php artisan schema:generate --dry-run

# Force overwrite existing files
php artisan schema:generate --force
```

### File Watcher

```bash
# Watch for changes and regenerate automatically
php artisan schema:watch

# Watch with custom interval
php artisan schema:watch --interval=5

# Watch in quiet mode
php artisan schema:watch --quiet
```

## ⚙️ Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="ArnaldoTomo\LaravelAutoSchema\AutoSchemaServiceProvider" --tag="autoscema-config"
```

### Configuration Options

```php
<?php

return [
    'output' => [
        'path' => resource_path('js/types'),
        'filename_case' => 'pascal', // pascal, camel, snake, kebab
        'export_format' => 'named', // named, default, namespace
    ],

    'models' => [
        'directories' => [
            app_path('Models'),
        ],
        'include_relationships' => true,
        'include_accessors' => true,
    ],

    'validation' => [
        'enabled' => true,
        'schema_format' => 'zod', // zod, yup, joi
        'include_form_requests' => true,
    ],

    'api' => [
        'generate_client' => true,
        'authentication' => 'sanctum', // sanctum, passport, none
    ],
];
```

## 🔧 Advanced Usage

### API Client Generation

When enabled, AutoSchema generates a complete API client:

```typescript
// Generated API client
import { userApi } from './types/api-client';

// Usage
const users = await userApi.getAll();
const user = await userApi.getById(1);
const newUser = await userApi.create({ name: 'John', email: 'john@example.com' });
```

### Custom Types

Handle complex relationships and custom types:

```php
class Product extends Model
{
    protected $casts = [
        'metadata' => 'json',
        'status' => ProductStatus::class, // Enum
    ];
    
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
```

Generated TypeScript:

```typescript
export interface Product {
  id: number;
  name: string;
  metadata: Record<string, any> | null;
  status: ProductStatus;
  
  // Relationships
  variants?: ProductVariant[];
}

export enum ProductStatus {
  ACTIVE = 'active',
  INACTIVE = 'inactive',
  DRAFT = 'draft',
}
```

### Integration with Frontend Frameworks

#### React + TypeScript

```typescript
import { User } from './types';

interface UserCardProps {
  user: User; // Fully typed!
}

const UserCard: React.FC<UserCardProps> = ({ user }) => {
  return (
    <div>
      <h3>{user.name}</h3>
      <p>{user.email}</p>
      {user.posts && (
        <span>{user.posts.length} posts</span>
      )}
    </div>
  );
};
```

#### Vue + TypeScript

```vue
<template>
  <div>
    <h3>{{ user.name }}</h3>
    <p>{{ user.email }}</p>
  </div>
</template>

<script setup lang="ts">
import type { User } from './types';

defineProps<{
  user: User;
}>();
</script>
```

#### Inertia.js

```typescript
import { User } from './types';

interface Props {
  users: User[];
}

const UsersIndex: React.FC<Props> = ({ users }) => {
  // users is fully typed!
};
```

## 🔄 Workflow Integration

### GitHub Actions

```yaml
name: Generate Types

on:
  push:
    paths:
      - 'app/Models/**'
      - 'database/migrations/**'
      - 'app/Http/Requests/**'

jobs:
  generate-types:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install dependencies
        run: composer install
        
      - name: Generate TypeScript types
        run: php artisan schema:generate
        
      - name: Commit generated types
        run: |
          git config --local user.email "action@github.com"
          git config --local user.name "GitHub Action"
          git add resources/js/types/
          git commit -m "Update TypeScript types" || exit 0
          git push
```

### Pre-commit Hook

```bash
#!/bin/sh
# .git/hooks/pre-commit

# Check if any model files changed
if git diff --cached --name-only | grep -q "app/Models\|database/migrations\|app/Http/Requests"; then
    echo "🔄 Regenerating TypeScript types..."
    php artisan schema:generate --force
    
    # Add generated files to commit
    git add resources/js/types/
    echo "✅ TypeScript types updated"
fi
```

## 🎯 Use Cases

### E-commerce Platform

```php
class Product extends Model
{
    protected $fillable = ['name', 'price', 'description'];
    
    protected $casts = [
        'price' => 'decimal:2',
        'metadata' => 'json',
        'status' => ProductStatus::class,
    ];
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
```

Generated types enable perfect frontend integration:

```typescript
// Product listing with full type safety
const ProductCard: React.FC<{ product: Product }> = ({ product }) => {
  return (
    <div className="product-card">
      <h3>{product.name}</h3>
      <p className="price">${product.price}</p>
      <p className="category">{product.category?.name}</p>
      <div className="variants">
        {product.variants?.map(variant => (
          <span key={variant.id}>{variant.name}</span>
        ))}
      </div>
    </div>
  );
};
```

### API Integration

```typescript
// Fully typed API calls
const products = await productApi.getAll({
  category: 'electronics',
  status: ProductStatus.ACTIVE
});

const product = await productApi.getById(1);
const newProduct = await productApi.create({
  name: 'New Product',
  price: 99.99,
  description: 'Amazing product'
});
```

### Form Validation

```typescript
// Form with automatic validation
const ProductForm: React.FC = () => {
  const {
    register,
    handleSubmit,
    formState: { errors }
  } = useForm<CreateProductInput>({
    resolver: zodResolver(createProductSchema)
  });
  
  const onSubmit = async (data: CreateProductInput) => {
    // data is fully typed and validated
    await productApi.create(data);
  };
  
  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <input {...register('name')} placeholder="Product name" />
      {errors.name && <span>{errors.name.message}</span>}
      
      <input {...register('price', { valueAsNumber: true })} />
      {errors.price && <span>{errors.price.message}</span>}
    </form>
  );
};
```

## 🔧 Troubleshooting

### Common Issues

#### Types not generating?

1. Check if models extend `Illuminate\Database\Eloquent\Model`
2. Ensure output directory is writable
3. Run with `--dry-run` to see what would be generated

```bash
php artisan schema:generate --dry-run
```

#### Relationships not showing?

1. Ensure relationships return `Illuminate\Database\Eloquent\Relations\Relation`
2. Check if `include_relationships` is enabled in config
3. Verify relationship methods are public

#### Custom types not working?

1. Add custom type mappings in configuration
2. Use `--force` to overwrite existing files
3. Check Laravel logs for errors

### Debug Mode

```bash
# Enable verbose output
php artisan schema:generate -v

# Show detailed analysis
php artisan schema:generate --dry-run -v
```

## 🧪 Testing

```bash
# Run package tests
composer test

# Run with coverage
composer test-coverage

# Run code formatting
composer format
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

```bash
# Clone repository
git clone https://github.com/arnaldo-tomo/laravel-autoscema.git
cd laravel-autoscema

# Install dependencies
composer install

# Run tests
composer test

# Format code
composer format
```

## 📜 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## 🛡️ Security

If you discover any security-related issues, please email arnaldo@example.com instead of using the issue tracker.

## 📄 License

Laravel AutoSchema is open-sourced software licensed under the [MIT license](LICENSE.md).

## 🙏 Credits

- [Arnaldo Tomo](https://github.com/arnaldo-tomo) - Creator and maintainer
- [All Contributors](../../contributors)

## 🎉 Support

- ⭐ Star the project on GitHub
- 🐛 Report issues on [GitHub Issues](https://github.com/arnaldo-tomo/laravel-autoscema/issues)
- 💡 Request features on [GitHub Discussions](https://github.com/arnaldo-tomo/laravel-autoscema/discussions)
- 📖 Read the [documentation](https://laravel-autoscema.dev)

---

Made with ❤️ by [Arnaldo Tomo](https://github.com/arnaldo-tomo) in Mozambique 🇲🇿