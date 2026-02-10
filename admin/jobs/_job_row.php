<tr class="hover:bg-gray-50">
    <td class="px-6 py-4">
        <div class="font-mono font-semibold text-blue-600">#
            <?php echo str_pad($job['job_id'], 5, '0', STR_PAD_LEFT); ?>
        </div>
        <div class="text-xs text-gray-500">
            <?php echo date('d/m/Y H:i', strtotime($job['opened_date'])); ?>
        </div>
    </td>
    <td class="px-6 py-4">
        <div class="font-medium text-gray-900">
            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
        </div>
        <div class="text-sm text-gray-500">
            <span class="font-mono bg-gray-100 px-2 py-0.5 rounded">
                <?php echo htmlspecialchars($job['license_plate']); ?>
            </span>
            <?php echo htmlspecialchars(($job['brand'] ?? '') . ' ' . ($job['model'] ?? '')); ?>
        </div>
    </td>
    <td class="px-6 py-4">
        <?php if ($job['job_category'] === 'repair'): ?>
            <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                🔧 งานซ่อม
            </span>
        <?php else: ?>
            <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                🔄 งานบริการ
            </span>
        <?php endif; ?>
        <?php if ($job['job_type'] === 'urgent'): ?>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 ml-1">
                🔥 ด่วน
            </span>
        <?php elseif ($job['job_type'] === 'appointment'): ?>
            <span
                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700 ml-1"
                title="<?php echo $job['appointment_date'] ? date('d/m/Y H:i', strtotime($job['appointment_date'])) : ''; ?>">
                📅
                <?php echo $job['appointment_date'] ? date('d/m', strtotime($job['appointment_date'])) : 'นัด'; ?>
            </span>
        <?php endif; ?>
    </td>
    <td class="px-6 py-4">
        <?php
        $statusColors = [
            'RECEIVED' => 'bg-gray-100 text-gray-700',
            'INSPECTING' => 'bg-yellow-100 text-yellow-700',
            'WAIT_PART' => 'bg-orange-100 text-orange-700',
            'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
            'COMPLETED' => 'bg-green-100 text-green-700',
            'WAIT_PAYMENT' => 'bg-purple-100 text-purple-700',
            'DELIVERED' => 'bg-emerald-100 text-emerald-700',
            'CANCELLED' => 'bg-red-100 text-red-700',
        ];
        $color = $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-700';
        ?>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $color; ?>">
            <?php echo htmlspecialchars($job['status_name'] ?? $job['status']); ?>
        </span>
    </td>
    <td class="px-6 py-4">
        <?php if ($job['tech_first_name']): ?>
            <div class="text-sm text-gray-900">
                <?php echo htmlspecialchars($job['tech_first_name'] . ' ' . $job['tech_last_name']); ?>
            </div>
        <?php else: ?>
            <span class="text-gray-400 text-sm">ยังไม่มอบหมาย</span>
        <?php endif; ?>
    </td>
    <td class="px-6 py-4 text-right">
        <a href="view.php?id=<?php echo $job['job_id']; ?>"
            class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                </path>
            </svg>
            ดูรายละเอียด
        </a>
    </td>
</tr>