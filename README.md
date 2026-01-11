# 教务管理系统 (Education Management System)

一个基于 Laravel 10 开发的教务管理系统，支持教师和学生两种角色的多表认证，实现了课程管理、账单管理、在线支付等完整功能。

## 📋 项目简介

本系统是一个完整的教务管理解决方案，支持教师创建和管理课程、发送账单，学生查看课程、支付账单等功能。系统集成了 Omise 支付网关，支持多币种支付（JPY、THB、SGD、USD），并实现了完整的支付流程和 Webhook 回调处理。

## ✨ 核心功能

### 👨‍🏫 教师功能

- **仪表板** - 查看课程数量、账单统计（待发送、已发送、已支付）
- **课程管理**
  - 创建课程（课程名、年月、费用、关联学生）
  - 查看课程列表（显示学生数量）
  - 编辑课程信息
  - 查看课程详情（包括学生列表和相关账单）
  - ⚠️ **课程创建后不可删除**（业务规则）
- **账单管理**
  - 创建账单（基于课程和学生）
  - 查看账单列表（支持筛选和分页）
  - 发送账单（将状态改为已发送，学生可见）
  - 批量发送账单
  - 查看账单详情（包括支付记录）
  - 重新发送已拒绝的账单

### 👨‍🎓 学生功能

- **仪表板** - 查看课程数量、待支付账单、已支付账单统计
- **我的课程**
  - 查看自己参与的课程列表
  - 查看课程详情（包括相关账单，不显示待发送的账单）
- **我的账单**
  - 查看自己的账单列表（不显示待发送的账单）
  - 查看账单详情
  - 支付账单（支持多币种选择：JPY、THB、SGD、USD）
  - 拒绝支付账单（使账单失效）

### 💳 支付功能

- **Omise 支付集成**
  - 支持多币种支付（JPY、THB、SGD、USD）
  - 前端使用 Omise.js 创建支付令牌
  - 后端调用 Omise API 创建支付请求
  - 自动处理货币转换（JPY 不乘以 100，其他货币乘以 100）
  - 金额验证（Omise 最小金额要求）
- **支付记录管理**
  - 记录所有支付尝试（成功、失败、处理中）
  - 保存完整的 Omise API 响应
  - 记录错误信息（支付失败时）
- **Webhook 回调处理**
  - 支持 Omise Webhook 事件处理
  - 签名验证确保安全性
  - 幂等性处理（防止重复处理）
  - 自动更新账单和支付记录状态

## 🛠 技术栈

- **后端框架**: Laravel 10.10
- **PHP 版本**: PHP 8.2+
- **数据库**: PostgreSQL
- **前端框架**: Bootstrap 5
- **支付网关**: Omise PHP SDK 2.18
- **前端支付**: Omise.js
- **测试框架**: PHPUnit 10

## 📦 安装步骤

### 1. 环境要求

- PHP 8.2 或更高版本
- Composer
- PostgreSQL 数据库
- Node.js 和 npm（用于前端资源编译）

### 2. 克隆项目

```bash
git clone git@github.com:zzxxin/edu-management-system.git
cd edu-management-system
```

### 3. 安装依赖

```bash
# 安装 PHP 依赖
composer install

# 安装前端依赖
npm install
```

### 4. 配置环境

复制 `.env.example` 为 `.env`：

```bash
cp .env.example .env
```

配置 `.env` 文件：

```env
# 应用配置
APP_NAME="教务管理系统"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Asia/Shanghai
APP_URL=http://localhost

# 数据库配置
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Omise 支付配置
OMISE_PUBLIC_KEY=pkey_test_xxxxx
OMISE_SECRET_KEY=skey_test_xxxxx
OMISE_CURRENCY=jpy
```

### 5. 生成应用密钥

```bash
php artisan key:generate
```

### 6. 运行数据库迁移

```bash
php artisan migrate
```

迁移将创建以下表：
- `teachers` - 教师表
- `students` - 学生表
- `courses` - 课程表
- `course_student` - 课程学生关联表
- `invoices` - 账单表
- `payments` - 支付记录表

### 7. 编译前端资源（可选）

```bash
# 开发环境
npm run dev

# 生产环境
npm run build
```

### 8. 启动开发服务器

```bash
php artisan serve
```

访问 `http://localhost:8000` 即可使用系统。

## 🔧 配置说明

### Omise 支付配置

1. 注册 Omise 账号：https://dashboard.omise.co/
2. 获取 API 密钥（测试环境使用 `pkey_test_` 和 `skey_test_` 开头的密钥）
3. 在 `.env` 文件中配置：
   ```env
   OMISE_PUBLIC_KEY=pkey_test_xxxxx
   OMISE_SECRET_KEY=skey_test_xxxxx
   OMISE_CURRENCY=jpy  # 默认货币：jpy, thb, sgd, usd
   ```
4. 配置 Webhook URL（在 Omise 控制台）：
   ```
   https://your-domain.com/api/omise/webhook
   ```

### 时区配置

系统默认使用 `Asia/Shanghai`（北京时间），可在 `.env` 文件中修改：

```env
APP_TIMEZONE=Asia/Shanghai
```

## 📚 数据库结构

### 核心表

- **teachers** - 教师信息（姓名、邮箱、密码）
- **students** - 学生信息（姓名、邮箱、密码、所属教师）
- **courses** - 课程信息（课程名、年月、费用、教师）
- **course_student** - 课程学生关联表（多对多关系）
- **invoices** - 账单信息（课程、学生、金额、状态、时间戳）
- **payments** - 支付记录（账单、Omise Charge ID、金额、货币、状态、响应）

### 关系说明

- 教师 ↔ 学生：一对多（一个教师有多个学生）
- 教师 ↔ 课程：一对多（一个教师创建多个课程）
- 课程 ↔ 学生：多对多（一个课程有多个学生，一个学生参与多个课程）
- 课程 ↔ 账单：一对多（一个课程有多个账单）
- 学生 ↔ 账单：一对多（一个学生有多个账单）
- 账单 ↔ 支付记录：一对多（一个账单可以有多次支付尝试）

## 🎯 功能特性

### 1. 多表认证

系统使用 Laravel 的多守卫认证，支持教师和学生分别登录：

- 教师守卫：`teacher`
- 学生守卫：`student`
- 统一登录接口，自动识别用户类型



### 2. 支付安全与幂等性

- **前端防重复提交**：支付按钮点击后立即禁用，显示加载状态
- **后端防重复处理**：使用数据库事务和行锁（`lockForUpdate()`）防止并发问题
- **Webhook 幂等性**：使用 `omise_charge_id` 作为唯一标识，支持重复调用
- **签名验证**：所有 Webhook 请求都验证签名，确保请求来自 Omise

### 3. 错误处理

- 友好的错误提示（将技术错误转换为用户可理解的提示）
- 完整的日志记录（所有关键操作都记录日志）
- 异常捕获（所有外部 API 调用都有异常处理）

## 🧪 测试

项目包含完整的单元测试和功能测试：

### 运行测试

```bash
php artisan test
```

### 测试覆盖

- **单元测试**
  - `tests/Unit/CourseModelTest.php` - 课程模型测试
  - `tests/Unit/InvoiceModelTest.php` - 账单模型测试
  - `tests/Unit/PaymentModelTest.php` - 支付模型测试
  - `tests/Unit/OmisePaymentServiceTest.php` - 支付服务测试

- **功能测试**
  - `tests/Feature/CourseTest.php` - 课程功能测试
  - `tests/Feature/InvoiceTest.php` - 账单功能测试
  - `tests/Feature/OmiseWebhookTest.php` - Webhook 功能测试

## 📖 API 路由

### Webhook 路由

```
POST /api/omise/webhook
```

处理 Omise Webhook 事件，自动更新账单和支付记录状态。

**安全要求**：
- 必须包含 `X-Omise-Signature` 请求头
- 签名验证通过后才会处理事件

## 🚀 部署

### 生产环境配置

1. 设置 `APP_ENV=production` 和 `APP_DEBUG=false`
2. 配置生产环境的 Omise 密钥（使用 `pkey_` 和 `skey_` 开头的密钥）
3. 配置 Webhook URL 为生产环境地址
4. 运行数据库迁移：`php artisan migrate --force`
5. 编译前端资源：`npm run build`
6. 优化应用：`php artisan config:cache` 和 `php artisan route:cache`

### 环境变量检查清单

- ✅ `APP_KEY` - 应用密钥
- ✅ `DB_*` - 数据库配置
- ✅ `OMISE_PUBLIC_KEY` - Omise 公钥
- ✅ `OMISE_SECRET_KEY` - Omise 私钥
- ✅ `OMISE_CURRENCY` - 默认货币代码
- ✅ `APP_TIMEZONE` - 时区设置

## 📝 代码规范

- 遵循 PSR-12 编码标准
- 使用 PHPDoc 注释
- 业务逻辑封装在模型和服务类中
- Controller 只负责调用和响应处理
- 避免 N+1 查询问题
- 使用框架自带功能，不重新封装框架核心功能

## 🔒 安全特性

- CSRF 保护（所有表单都包含 CSRF 令牌）
- SQL 注入防护（使用 Eloquent ORM 参数绑定）
- XSS 防护（Blade 模板自动转义）
- 密码加密（使用 Laravel 的 Hash 功能）
- Webhook 签名验证（确保请求来自 Omise）
- 支付防重复提交（前端和后端双重保护）

## 📄 许可证

MIT License

## 👥 贡献

欢迎提交 Issue 和 Pull Request！

## 📞 联系方式

如有问题，请通过 GitHub Issues 联系。

---

**注意**：本系统是教务管理系统的核心功能实现，专注于课程管理、账单管理和支付功能。系统使用 Laravel 框架的标准功能，遵循最佳实践，确保代码质量和可维护性。
