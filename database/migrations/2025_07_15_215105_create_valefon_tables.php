<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('usercode', 10)->primary();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->string('membercode', 10)->primary();
            $table->string('usercode', 10)->unique();
            $table->foreign('usercode')->references('usercode')->on('users')->onDelete('cascade');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_code', 10)->nullable();
            $table->text('account_number', )->nullable();
            $table->string('paystack_customer_code', 50)->nullable();
            $table->string('paystack_recipient_code', 225)->nullable();
            $table->string('access_level', 20)->default('member');
            $table->string('status', 20)->default('pending');
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->index('usercode');
            $table->index('status');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->string('transid', 30)->primary();
            $table->string('usercode', 10);
            $table->foreign('usercode')->references('usercode')->on('users')->onDelete('cascade');
            $table->string('transtype', 20); // payment, withdrawal, reinvestment, profit_distribution
            $table->string('transcode', 10);
            $table->string('source', 20)->nullable(); // capital or profit
            $table->decimal('amount', 10, 2);
            $table->string('reference')->unique();
            $table->string('status', 15)->default('pending');
            $table->string('method', 20)->nullable();
            $table->string('period', 4)->nullable(); // e.g., '2025'
            $table->string('paystack_txnid', 20)->nullable();
            $table->string('channel', 20)->nullable();
            $table->string('currency', 10)->default('NGN');
            $table->string('description')->nullable();
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->ipAddress('ip')->nullable();
            $table->string('device')->nullable();
            $table->index('usercode');
            $table->index('reference');
            $table->index('transid');
            $table->index('status');
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->string('walletid', 10)->primary();
            $table->string('usercode', 10)->unique();
            $table->foreign('usercode')->references('usercode')->on('users')->onDelete('cascade');
            $table->decimal('capital', 12, 2)->default(0); // locked investment
            $table->decimal('profit_balance', 12, 2)->default(0); // available profit
            $table->decimal('lifetime_profit', 12, 2)->default(0); // total earned ever
            $table->decimal('reinvested', 12, 2)->default(0);
            $table->date('last_pdst')->nullable();
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->index('usercode');
            $table->index('walletid');
        });

        // Schema::create('profits', function (Blueprint $table) {
        //     $table->string('profitid', 10)->primary();
        //     $table->string('usercode', 10);
        //     $table->foreign('usercode')->references('usercode')->on('users')->onDelete('cascade');
        //     $table->string('batchid', 10);
        //     $table->foreign('batchid')->references('batchid')->on('profit_batches')->onDelete('cascade');
        //     $table->decimal('amount', 12, 2);
        //     $table->timestamp('entdate')->nullable()->default(now());
        //     $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
        //     $table->softDeletes();
        //     $table->index(['usercode', 'batchid']);
        // });

        Schema::create('profit_batches', function (Blueprint $table) {
            $table->string('batchid', 10)->primary();
            $table->decimal('total_capital', 15, 2);
            $table->decimal('percentage_distr', 5, 2); // e.g., 20% set by management
            $table->decimal('total_profit_dist', 15, 2);
            $table->year('period'); // e.g., 2025;
            $table->string('status', 20)->default('completed');
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
            $table->string('source', 50)->nullable();
        });

        Schema::create('profit_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batchid', 10); // Reduced from 50 for consistency
            $table->string('usercode', 10); // Reduced from 50 for consistency
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('used_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'reinvested'])->default('pending');
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();

            $table->foreign('batchid')->references('batchid')->on('profit_batches')->onDelete('cascade');
            $table->foreign('usercode')->references('usercode')->on('users')->onDelete('cascade');
            $table->unique(['batchid', 'usercode']);
            $table->index('status'); // Added for filtering
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique(); // e.g., 'current_period'
            $table->string('value', 50); // e.g., '2025'
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
            $table->string('updated_by', 10)->nullable(); // usercode of admin who updated
            $table->softDeletes();
            $table->index('key');
        });

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->string('usercode', 10);
            $table->foreign('usercode')->references('usercode')->on('users')->onDelete('cascade');
            $table->ipAddress('ip_address');
            $table->string('device_info')->nullable();
            $table->string('status'); // success or failed
            $table->timestamp('entdate')->nullable()->default(now());
            $table->timestamp('upddate')->nullable()->useCurrentOnUpdate();
            $table->index('usercode');
        });
    }

    public function down()
    {
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('profit_allocations');
        Schema::dropIfExists('profit_batches');
        // Schema::dropIfExists('profits');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('members');
        Schema::dropIfExists('users');
        Schema::dropIfExists('settings');
    }
};
