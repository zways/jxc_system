<template>
    <div ref="rootRef" class="barcode-scan-input" data-barcode-scan>
        <el-input
            ref="inputRef"
            v-model="inputValue"
            :placeholder="placeholder"
            :size="size"
            :disabled="disabled"
            clearable
            @keyup.enter.prevent="handleLookup"
        >
            <template #prefix>
                <el-icon v-if="loading" class="is-loading">
                    <Loading />
                </el-icon>
                <el-icon v-else>
                    <Search />
                </el-icon>
            </template>
            <template #append>
                <el-button :icon="Search" :loading="loading" @click="handleLookup">查询</el-button>
            </template>
        </el-input>
        <span v-if="hint" class="barcode-hint">{{ hint }}</span>
    </div>
</template>

<script>
import { ref, onMounted, nextTick, defineComponent } from "vue";
import { Search, Loading } from "@element-plus/icons-vue";
import { ElMessage } from "element-plus";

export default defineComponent({
    name: "BarcodeScanInput",
    components: { Search, Loading },
    props: {
        placeholder: {
            type: String,
            default: "扫码或输入条码/编码，按回车查询",
        },
        size: {
            type: String,
            default: "default",
        },
        disabled: Boolean,
        hint: {
            type: String,
            default: "支持 PDA 扫码枪：扫码后自动识别并添加商品",
        },
        /** 进入时是否自动聚焦，便于 PDA 扫码无需先点击 */
        autofocus: {
            type: Boolean,
            default: true,
        },
    },
    emits: ["product", "not-found"],
    setup(props, { emit, expose }) {
        const rootRef = ref(null);
        const inputRef = ref(null);
        const inputValue = ref("");
        const loading = ref(false);

        const handleLookup = async () => {
            const q = (inputValue.value || "").trim();
            if (!q) {
                ElMessage.warning("请输入或扫描条码/编码");
                return;
            }
            loading.value = true;
            try {
                // 优先按条码查询，若未找到则按编码查询
                for (const param of ["barcode", "code"]) {
                    try {
                        const res = await window.axios.get("products/lookup", { params: { [param]: q } });
                        const product = res.data?.data;
                        if (product) {
                            emit("product", product);
                            inputValue.value = "";
                            return;
                        }
                    } catch (err) {
                        if (err.response?.status !== 404 || param === "code") {
                            throw err;
                        }
                    }
                }
                emit("not-found", q);
                ElMessage.warning(`未找到条码/编码为「${q}」的商品`);
            } catch (e) {
                const msg = e.response?.data?.message || "查询失败";
                if (e.response?.status === 404) emit("not-found", q);
                ElMessage.warning(msg);
            } finally {
                loading.value = false;
            }
        };

        const focus = () => {
            // 优先从根节点查找原生 input，弹窗内更可靠
            const root = rootRef.value;
            const fromRoot = root?.querySelector?.("input");
            if (fromRoot && typeof fromRoot.focus === "function") {
                fromRoot.focus();
                return;
            }
            const el = inputRef.value;
            if (!el) return;
            const nativeInput = el.ref ?? el.input ?? el.$el?.querySelector?.("input");
            if (nativeInput && typeof nativeInput.focus === "function") {
                nativeInput.focus();
            } else if (typeof el.focus === "function") {
                el.focus();
            }
        };

        const clear = () => {
            inputValue.value = "";
        };

        onMounted(() => {
            if (props.autofocus) {
                // 延迟聚焦：确保 el-input 内部原生 input 已挂载，且避免被其他元素抢焦点
                nextTick(() => {
                    focus();
                    setTimeout(focus, 50);
                });
            }
        });

        expose({ focus, clear });
        return { rootRef, inputRef, inputValue, loading, handleLookup, focus, clear };
    },
});
</script>

<style scoped>
.barcode-scan-input {
    display: inline-flex;
    flex-direction: column;
    gap: 4px;
}
.barcode-hint {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
</style>
