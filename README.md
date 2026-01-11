# 教务管理系统

## 项目简介

这是一个基于 Laravel 10 开发的教务管理系统，支持教师和学生两种角色的多表认证，实现了课程管理、账单管理等功能。

## 技术栈

- PHP 8.2
- Laravel 10
- Laravel Passport ^11.8（用于API认证）
- PostgreSQL
- Bootstrap 5（前端UI）
- Omise（支付集成）

## 安装步骤

### 1. 安装依赖

```bash
# 使用Herd安装Composer依赖
herd composer install

# 如果需要安装Laravel Passport（如果需要API认证）
herd composer require laravel/passport:^11.8

# 如果需要安装Omise PHP SDK（用于支付功能）
herd composer require omise/omise-php:^2.20
```

### 2. 配置环境

复制 `.env.example` 为 `.env` 并配置：

```bash
cp .env.example .env
```

配置 `.env` 文件：

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=123456

# Omise 支付配置（详见 OMISE_SETUP.md）
OMISE_PUBLIC_KEY=your_omise_public_key
OMISE_SECRET_KEY=your_omise_secret_key
OMISE_CURRENCY=thb
```

### 3. 生成应用密钥

```bash
herd php artisan key:generate
```

### 4. 运行数据库迁移

**注意：** 运行迁移前，请确保 `teachers` 和 `students` 表已创建。请参考 `DATABASE_ADJUSTMENTS.md` 文档。

```bash
herd php artisan migrate
```

### 5. 配置Laravel Passport（如果需要）

```bash
herd php artisan passport:install
```

## 数据库说明

本项目需要以下数据库表：

1. **teachers** - 教师表（需要在后台管理系统中创建）
2. **students** - 学生表（需要在后台管理系统中创建）
3. **courses** - 课程表（通过迁移创建）
4. **invoices** - 账单表（通过迁移创建）

**注意**：课程和学生通过 `course_student` 中间表建立多对多关系。创建课程时需要选择学生，创建账单时只能选择该课程已关联的学生。

详细的 `teachers` 和 `students` 表结构要求，请参考 `DATABASE_ADJUSTMENTS.md` 文档。

## 功能说明

### 教师功能

1. **登录** - 使用邮箱和密码登录
2. **仪表板** - 查看统计数据
3. **课程管理**
   - 创建课程（设定课程名、年月、费用、学生）
   - 查看课程列表
   - 编辑课程
   - 删除课程
   - 查看课程详情（包括学生列表和相关账单）
4. **账单管理**
   - 创建账单
   - 查看账单列表
   - 发送账单（将状态改为已发送，学生可以看到）
   - 批量发送账单

### 学生功能

1. **登录** - 使用邮箱和密码登录（与教师使用同一登录接口）
2. **仪表板** - 查看统计数据
3. **我的课程**
   - 查看自己参与的课程列表
   - 查看课程详情（包括相关账单）
4. **我的账单**
   - 查看自己的账单列表
   - 查看账单详情
   - 支付账单（使用Omise集成）

## 路由说明

### 认证路由

- `GET /login` - 登录页面
- `POST /login` - 处理登录
- `POST /logout` - 登出

### 教师路由

- `GET /teacher/dashboard` - 教师仪表板
- `GET /teacher/courses` - 课程列表
- `GET /teacher/courses/create` - 创建课程表单
- `POST /teacher/courses` - 存储课程
- `GET /teacher/courses/{course}` - 课程详情
- `GET /teacher/courses/{course}/edit` - 编辑课程表单
- `PUT /teacher/courses/{course}` - 更新课程
- `DELETE /teacher/courses/{course}` - 删除课程
- `GET /teacher/invoices` - 账单列表
- `GET /teacher/invoices/create` - 创建账单表单
- `POST /teacher/invoices` - 存储账单
- `POST /teacher/invoices/{invoice}/send` - 发送账单
- `POST /teacher/invoices/batch-send` - 批量发送账单

### 学生路由

- `GET /student/dashboard` - 学生仪表板
- `GET /student/courses` - 我的课程列表
- `GET /student/courses/{id}` - 课程详情
- `GET /student/invoices` - 我的账单列表
- `GET /student/invoices/{invoice}` - 账单详情
- `POST /student/invoices/{invoice}/pay` - 支付账单

## 代码特点

### 1. 避免N+1查询问题

所有列表查询都使用了 Laravel 的 `with()` 方法进行预加载：

```php
// 课程列表 - 预加载学生关联
$courses = Course::where('teacher_id', $teacher->id)
    ->with(['students'])
    ->paginate(15);

// 账单列表 - 预加载课程和学生关联
$invoices = Invoice::whereHas('course', function ($query) use ($teacher) {
        $query->where('teacher_id', $teacher->id);
    })
    ->with(['course', 'student'])
    ->paginate(15);
```

### 2. 使用框架自带功能

- 使用 Laravel 的认证系统（多守卫认证）
- 使用 Laravel 的验证器
- 使用 Laravel 的模型关联
- 使用 Laravel 的数据库迁移
- 使用 Laravel 的中间件

### 3. 业务逻辑封装

- 课程状态判断封装在模型中（`Invoice` 模型的 `isPending()`, `isSent()`, `isPaid()` 方法）
- 统一认证提供者（`UnifiedUserProvider`）用于支持多表认证

### 4. 单元测试

项目包含完整的单元测试和功能测试：

- `tests/Feature/CourseTest.php` - 课程功能测试
- `tests/Feature/InvoiceTest.php` - 账单功能测试
- `tests/Unit/CourseModelTest.php` - 课程模型测试
- `tests/Unit/InvoiceModelTest.php` - 账单模型测试

运行测试：

```bash
herd php artisan test
```

## 注意事项

1. **数据库表调整**：`teachers` 和 `students` 表需要在后台管理系统中创建，请参考 `DATABASE_ADJUSTMENTS.md` 文档。

2. **Omise配置**：需要在 `.env` 文件中配置 Omise 的公钥和私钥。详细配置说明请参考 [OMISE_SETUP.md](OMISE_SETUP.md)。

3. **多表认证**：教师和学生使用不同的守卫（`teacher` 和 `student`），但使用同一个登录接口。

4. **支付功能**：支付功能需要配置 Omise，并在前端使用 Omise.js 创建支付令牌。

## 开发和部署

### 本地开发

1. 确保 PostgreSQL 数据库已启动
2. 运行迁移：`herd php artisan migrate`
3. 启动开发服务器：`herd php artisan serve`
4. 访问：`http://localhost:8000`

### 部署到Heroku

详细的 Heroku 部署指南请参考 [HEROKU_DEPLOYMENT.md](HEROKU_DEPLOYMENT.md) 文档。

**快速部署步骤：**

1. 创建 Heroku 应用：`heroku create your-app-name`
2. 添加 PostgreSQL：`heroku addons:create heroku-postgresql:mini`
3. 配置环境变量（见 HEROKU_DEPLOYMENT.md）
4. 部署代码：`git push heroku main`
5. 运行迁移：`heroku run php artisan migrate --force`
6. 配置 Passport：`heroku run php artisan passport:install --force`
7. 配置 Omise Webhook：`https://your-app-name.herokuapp.com/api/omise/webhook`

**重要注意事项：**
- ✅ 确保 `Procfile` 文件存在
- ✅ 配置所有必要的环境变量
- ✅ 使用生产环境的 Omise 密钥
- ✅ 配置 Omise Webhook URL 为 Heroku 应用地址
- ✅ 静态资源需要编译后提交
- ✅ Passport 密钥需要正确配置

## 许可证

MIT
