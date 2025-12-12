<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('support_emails', function (Blueprint $table) {
            // Thêm cột attachment_path sau cột has_attachment
            // nullable() nghĩa là cho phép null (vì không phải lúc nào khách cũng gửi ảnh)
            if (!Schema::hasColumn('support_emails', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('has_attachment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('support_emails', function (Blueprint $table) {
            if (Schema::hasColumn('support_emails', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });
    }
};