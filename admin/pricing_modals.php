<!-- Edit Pricing Plan Modal -->
<div id="pricingEditModal"
    class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-fade-in overflow-y-auto">
    <div
        class="glass-card w-full max-w-2xl my-auto rounded-[2.5rem] border border-white/10 shadow-2xl overflow-hidden animate-scale-in">
        <div
            class="bg-gradient-to-r from-green-600/20 to-blue-600/20 p-6 border-b border-white/5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-500/20 rounded-xl flex items-center justify-center text-green-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white">
                    <?php echo __('edit_plan'); ?>
                </h3>
            </div>
            <button onclick="closeEditPlan()" class="p-2 hover:bg-white/5 rounded-full transition-colors">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form method="POST" class="p-8 space-y-6 text-left rtl:text-right">
            <input type="hidden" name="plan_id" id="edit_plan_id">
            <input type="hidden" name="active_tab" value="pricing">

            <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('plan_name'); ?>
                        </label>
                        <input type="text" name="plan_name_ar" id="edit_plan_name_ar" class="setting-input"
                            placeholder="العربي" required>
                        <input type="text" name="plan_name_en" id="edit_plan_name_en" class="setting-input mt-2"
                            placeholder="English" required>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('price'); ?>
                        </label>
                        <input type="number" step="0.01" name="plan_price" id="edit_plan_price" class="setting-input"
                            required>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('currency'); ?>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="plan_currency_ar" id="edit_plan_currency_ar" class="setting-input"
                                placeholder="عربي">
                            <input type="text" name="plan_currency_en" id="edit_plan_currency_en" class="setting-input"
                                placeholder="English">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('billing_period'); ?>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="plan_period_ar" id="edit_plan_period_ar" class="setting-input"
                                placeholder="عربي">
                            <input type="text" name="plan_period_en" id="edit_plan_period_en" class="setting-input"
                                placeholder="English">
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('plan_description'); ?>
                        </label>
                        <textarea name="plan_desc_ar" id="edit_plan_desc_ar" class="setting-input resize-none" rows="2"
                            placeholder="عربي"></textarea>
                        <textarea name="plan_desc_en" id="edit_plan_desc_en" class="setting-input resize-none mt-2"
                            rows="2" placeholder="English"></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('plan_features'); ?>
                        </label>
                        <textarea name="plan_features" id="edit_plan_features" class="setting-input resize-none"
                            rows="3" placeholder="<?php echo __('one_feature_per_line'); ?>"></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('button_text'); ?>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="plan_btn_ar" id="edit_plan_btn_ar" class="setting-input"
                                placeholder="عربي">
                            <input type="text" name="plan_btn_en" id="edit_plan_btn_en" class="setting-input"
                                placeholder="English">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('button_url'); ?>
                        </label>
                        <input type="url" name="plan_btn_url" id="edit_plan_btn_url" class="setting-input">
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest">
                            <?php echo __('display_order'); ?>
                        </label>
                        <input type="number" name="plan_order" id="edit_plan_order" class="setting-input">
                    </div>

                    <label class="flex items-center gap-2 text-white cursor-pointer">
                        <input type="checkbox" name="plan_featured" id="edit_plan_featured"
                            class="w-4 h-4 rounded border-gray-600 text-green-600 focus:ring-green-500">
                        <span class="text-sm">
                            <?php echo __('is_featured'); ?>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex gap-4 pt-6 border-t border-white/5">
                <button type="submit" name="edit_pricing_plan"
                    class="flex-1 py-4 bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-500 hover:to-blue-500 text-white font-bold rounded-2xl transition-all shadow-xl shadow-green-600/20 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <?php echo __('save_changes'); ?>
                </button>
                <button type="button" onclick="closeEditPlan()"
                    class="flex-1 py-4 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl border border-white/10 transition-all">
                    <?php echo __('cancel'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Pricing Plan Modal -->
<div id="pricingDeleteModal"
    class="fixed inset-0 z-[70] hidden flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-md animate-fade-in">
    <div
        class="glass-card w-full max-w-md p-0 rounded-[2.5rem] border border-red-500/20 shadow-2xl text-center animate-scale-in overflow-hidden">
        <div class="p-8">
            <div
                class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 text-red-500">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">
                <?php echo __('delete_plan'); ?>؟
            </h3>
            <p class="text-gray-400 mb-8">
                <?php echo __('undone_action_warning'); ?>
            </p>

            <form method="POST" class="flex flex-col gap-3">
                <input type="hidden" name="delete_pricing_plan" id="delete_plan_id">
                <input type="hidden" name="active_tab" value="pricing">
                <button type="submit"
                    class="w-full py-4 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl transition-all shadow-xl shadow-red-600/20">
                    <?php echo __('confirm_delete'); ?>
                </button>
                <button type="button" onclick="closeDeletePlanModal()"
                    class="w-full py-4 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl border border-white/10 transition-all">
                    <?php echo __('cancel'); ?>
                </button>
            </form>
        </div>
    </div>
</div>