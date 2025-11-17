<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            // 1. id: bigint, auto_increment
            $table->bigIncrements('id');
            
            // 2. fullName: varchar(150), Not Null
            $table->string('fullName', 150);
            
            // 3. email: varchar(150), Not Null
            $table->string('email', 150);
            
            // 4. phone: varchar(20), Null
            $table->string('phone', 20)->nullable();
            
            // 5. password: varchar(255), Not Null
            $table->string('password'); // 255 là độ dài mặc định
            
            // 6. avatar_url: varchar(255), Null
            $table->string('avatar_url')->nullable();
            
            // 7. status: varchar(20), Not Null, Default: 'active'
            $table->string('status', 20)->default('active');
            
            // 8. created_at: timestamp, Not Null, Default: now()
            // 9. updated_at: timestamp, Not Null, Default: now()
            // Dùng 2 dòng này để khớp 100% với "Not Null" và "Default: now()"
            // (Nếu dùng $table->timestamps() thì 2 cột này sẽ là NULLABLE)
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // 10. deleted_at: timestamp, Null
            $table->softDeletes(); // Tạo cột deleted_at y hệt
            
            // 11. remember_token: varchar(100), Not Null
            // (Helper $table->rememberToken() sẽ tạo là NULLABLE)
            $table_>string('remember_token', 100);

            // 12. email_verified_at: datetime, Not Null
            // (Helper $table->timestampEmailVerifiedAt() sẽ tạo là TIMESTAMP và NULLABLE)
            $table_>dateTime('email_verified_at');

            // 13. username: varchar(100), Not Null
            $table->string('username', 100);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admins');
    }
};