# Nacos-Agent-PHP

## Requirements

- PHP ^7.0
- pcntl
- posix
## Installation

```powershell
composer install
```

### 启动
```powershell
php appPath/bin/start start
```

### 打包

https://github.com/box-project/box

https://github.com/box-project/homebrew-box

####安装打包工具
```powershell
brew tap humbug/box
brew install box
box -v
```

####打包
```powershell
box compile
```