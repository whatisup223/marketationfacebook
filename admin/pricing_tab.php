<!-- Pricing Management Tab -->
<div id="pricing-tab" class="tab-content hidden space-y-8">
    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
        <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
            <span class="w-2 h-8 bg-green-500 rounded-full"></span>
            <?php echo __('pricing'); ?>
        </h3>

        <!-- Add New Plan Form -->
        <div class="bg-white/5 p-6 rounded-xl border border-white/5 mb-8">
            <h4 class="text-white font-bold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <?php echo __('add_pricing_plan'); ?>
            </h4>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="space-y-4">
                    <input type="text" name="plan_name_ar" class="setting-input"
                        placeholder="<?php echo __('plan_name'); ?> (AR)">
                    <input type="text" name="plan_name_en" class="setting-input"
                        placeholder="<?php echo __('plan_name'); ?> (EN)">
                    <input type="number" step="0.01" name="plan_price" class="setting-input"
                        placeholder="<?php echo __('price'); ?>">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="plan_currency_ar" class="setting-input"
                            placeholder="<?php echo __('currency'); ?> (AR)" value="ريال">
                        <input type="text" name="plan_currency_en" class="setting-input"
                            placeholder="<?php echo __('currency'); ?> (EN)" value="SAR">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="plan_period_ar" class="setting-input"
                            placeholder="<?php echo __('billing_period'); ?> (AR)" value="شهرياً">
                        <input type="text" name="plan_period_en" class="setting-input"
                            placeholder="<?php echo __('billing_period'); ?> (EN)" value="Monthly">
                    </div>
                </div>
                <div class="space-y-4">
                    <textarea name="plan_desc_ar" class="setting-input" rows="2"
                        placeholder="<?php echo __('plan_description'); ?> (AR)"></textarea>
                    <textarea name="plan_desc_en" class="setting-input" rows="2"
                        placeholder="<?php echo __('plan_description'); ?> (EN)"></textarea>
                    <textarea name="plan_features" class="setting-input" rows="3"
                        placeholder="<?php echo __('plan_features'); ?> - <?php echo __('one_feature_per_line'); ?>"></textarea>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="plan_btn_ar" class="setting-input"
                            placeholder="<?php echo __('button_text'); ?> (AR)" value="اشترك الآن">
                        <input type="text" name="plan_btn_en" class="setting-input"
                            placeholder="<?php echo __('button_text'); ?> (EN)" value="Subscribe Now">
                    </div>
                    <input type="url" name="plan_btn_url" class="setting-input"
                        placeholder="<?php echo __('button_url'); ?>">
                    <input type="number" name="plan_order" class="setting-input"
                        placeholder="<?php echo __('display_order'); ?>" value="0">
                    <label class="flex items-center gap-2 text-white cursor-pointer">
                        <input type="checkbox" name="plan_featured"
                            class="w-4 h-4 rounded border-gray-600 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <?php echo __('is_featured'); ?>
                        </span>
                    </label>
                    <button type="submit" name="add_pricing_plan"
                        class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl transition-all">
                        <?php echo __('add_pricing_plan'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Existing Plans List -->
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead>
                    <tr class="text-gray-500 border-b border-white/5 uppercase text-[10px] font-black tracking-widest">
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">
                            <?php echo __('plan_name'); ?>
                        </th>
                        <th class="px-4 py-3">
                            <?php echo __('price'); ?>
                        </th>
                        <th class="px-4 py-3">
                            <?php echo __('is_featured'); ?>
                        </th>
                        <th class="px-4 py-3">
                            <?php echo __('actions'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php
                    $priceStmt = $pdo->query("SELECT * FROM pricing_plans ORDER BY display_order ASC, id DESC");
                    while ($plan = $priceStmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-4 py-4 text-gray-500 font-mono">
                                <?php echo $plan['display_order']; ?>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-white">
                                    <?php echo $plan['plan_name_' . $lang]; ?>
                                </div>
                                <div class="text-[10px] text-gray-500">
                                    <?php echo $plan['description_' . $lang]; ?>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-green-400 font-bold">
                                <?php echo $plan['price']; ?>
                                <?php echo $plan['currency_' . $lang]; ?> /
                                <?php echo $plan['billing_period_' . $lang]; ?>
                            </td>
                            <td class="px-4 py-4">
                                <?php if ($plan['is_featured']): ?>
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-yellow-500/20 text-yellow-400">
                                        <?php echo __('most_popular'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-right rtl:text-left flex items-center justify-end gap-2">
                                <button type="button" onclick='openEditPlan(<?php echo json_encode($plan); ?>)'
                                    class="p-2 text-blue-400 hover:bg-blue-500/20 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5M18.364 5.364a9 9 0 1112.728 12.728L5.364 18.364m12.728-12.728L5.364 5.364">
                                        </path>
                                    </svg>
                                </button>
                                <button type="button" onclick="confirmDeletePlan(<?php echo $plan['id']; ?>)"
                                    class="p-2 text-red-500 hover:bg-red-500/20 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function openEditPlan(plan) {
        document.getElementById('edit_plan_id').value = plan.id;
        document.getElementById('edit_plan_name_ar').value = plan.plan_name_ar;
        document.getElementById('edit_plan_name_en').value = plan.plan_name_en;
        document.getElementById('edit_plan_price').value = plan.price;
        document.getElementById('edit_plan_currency_ar').value = plan.currency_ar;
        document.getElementById('edit_plan_currency_en').value = plan.currency_en;
        document.getElementById('edit_plan_period_ar').value = plan.billing_period_ar;
        document.getElementById('edit_plan_period_en').value = plan.billing_period_en;
        document.getElementById('edit_plan_desc_ar').value = plan.description_ar;
        document.getElementById('edit_plan_desc_en').value = plan.description_en;
        document.getElementById('edit_plan_features').value = plan.features;
        document.getElementById('edit_plan_btn_ar').value = plan.button_text_ar;
        document.getElementById('edit_plan_btn_en').value = plan.button_text_en;
        document.getElementById('edit_plan_btn_url').value = plan.button_url;
        document.getElementById('edit_plan_order').value = plan.display_order;
        document.getElementById('edit_plan_featured').checked = plan.is_featured == 1;

        document.getElementById('pricingEditModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditPlan() {
        document.getElementById('pricingEditModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function confirmDeletePlan(id) {
        document.getElementById('delete_plan_id').value = id;
        document.getElementById('pricingDeleteModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeDeletePlanModal() {
        document.getElementById('pricingDeleteModal').classList.add('hidden');
        document.body.style.overflow = '';
    }
</script>