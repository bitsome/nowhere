import { reactive, ref } from 'vue';
import { apiCreateVehicle, apiDeleteVehicle, apiMyVehicles, apiUpdateVehicle } from '../api/driver';
import { getApiErrorMessage } from '../api/client';

/**
 * 기사 운영 — 가용 상태 토글, 차량 관리(등록/수정/삭제)를 담당한다.
 * 오늘 요약은 useDriverTodayStats 가 맡는다.
 *
 * @param {object} options
 * @param {object} options.message naive-ui message
 * @param {object} options.driver useDriverStore
 * @param {() => Promise<void>} [options.loadTodayStats] 상태를 바꾼 뒤 오늘 요약을 다시 불러올 콜백 (선택)
 */
export function useDriverWorkspace({ message, driver, loadTodayStats }) {
    const vehicles = ref([]);
    const vehicleLoading = ref(true);
    const vehicleFormOpen = ref(false);
    const editingVehicleId = ref(null);
    const savingVehicle = ref(false);

    const vehicleForm = reactive({
        name: '',
        type: '',
        license_plate: '',
        color: '',
        capacity: 0,
        luggage_capacity: 0,
        insurance_expires_at: null,
        is_default: false,
    });

    // 온라인/오프라인 토글 (운행 중이면 끌 수 없다)
    const toggleDriverStatus = async () => {
        if (driver.status === 'on_trip') {
            message.warning('운행 중에는 상태를 변경할 수 없습니다.');

            return;
        }

        try {
            await driver.setStatus(driver.isOnline ? 'offline' : 'online');
            message.success(driver.status === 'online' ? '온라인으로 전환되었습니다.' : '오프라인으로 전환되었습니다.');
            // 온라인 시간은 상태에 따라 달라진다 — 켠 화면의 요약도 함께 맞춘다
            await loadTodayStats?.();
        } catch (e) {
            message.error(getApiErrorMessage(e, '상태 변경에 실패했습니다.'));
        }
    };

    const loadVehicles = async () => {
        vehicleLoading.value = true;

        try {
            const { data } = await apiMyVehicles();
            vehicles.value = data.data;
        } catch {
            vehicles.value = [];
        } finally {
            vehicleLoading.value = false;
        }
    };

    const openVehicleForm = (vehicle = null) => {
        editingVehicleId.value = vehicle?.id ?? null;
        vehicleForm.name = vehicle?.name ?? '';
        vehicleForm.type = vehicle?.type ?? '';
        vehicleForm.license_plate = vehicle?.license_plate ?? '';
        vehicleForm.color = vehicle?.color ?? '';
        vehicleForm.capacity = vehicle?.capacity ?? 0;
        vehicleForm.luggage_capacity = vehicle?.luggage_capacity ?? 0;
        vehicleForm.insurance_expires_at = vehicle?.insurance_expires_at ? new Date(vehicle.insurance_expires_at).getTime() : null;
        vehicleForm.is_default = vehicle?.is_default ?? false;
        vehicleFormOpen.value = true;
    };

    const toDateString = (ms) => {
        const date = new Date(ms);
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${date.getFullYear()}-${month}-${day}`;
    };

    const closeVehicleForm = () => {
        vehicleFormOpen.value = false;
        editingVehicleId.value = null;
    };

    const saveVehicle = async () => {
        if (!vehicleForm.name.trim()) {
            message.warning('차량 이름을 입력해 주세요.');

            return;
        }

        savingVehicle.value = true;

        try {
            const payload = {
                name: vehicleForm.name.trim(),
                type: vehicleForm.type.trim(),
                license_plate: vehicleForm.license_plate.trim(),
                color: vehicleForm.color.trim(),
                capacity: vehicleForm.capacity || 0,
                luggage_capacity: vehicleForm.luggage_capacity || 0,
                insurance_expires_at: vehicleForm.insurance_expires_at ? toDateString(vehicleForm.insurance_expires_at) : null,
                is_default: vehicleForm.is_default,
            };

            if (editingVehicleId.value) {
                await apiUpdateVehicle(editingVehicleId.value, payload);
                message.success('차량이 수정되었습니다.');
            } else {
                await apiCreateVehicle(payload);
                message.success('차량이 등록되었습니다.');
            }

            closeVehicleForm();
            await loadVehicles();
        } catch (e) {
            message.error(getApiErrorMessage(e, '차량 저장에 실패했습니다.'));
        } finally {
            savingVehicle.value = false;
        }
    };

    const removeVehicle = async (vehicle) => {
        try {
            await apiDeleteVehicle(vehicle.id);
            message.success('차량이 삭제되었습니다.');
            await loadVehicles();
        } catch (e) {
            message.error(getApiErrorMessage(e, '차량 삭제에 실패했습니다.'));
        }
    };

    return {
        vehicles,
        vehicleLoading,
        vehicleFormOpen,
        editingVehicleId,
        savingVehicle,
        vehicleForm,
        toggleDriverStatus,
        loadVehicles,
        openVehicleForm,
        closeVehicleForm,
        saveVehicle,
        removeVehicle,
    };
}
