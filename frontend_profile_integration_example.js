/**
 * Frontend Integration Example: Personal Profile API
 * 
 * Contoh penggunaan API personal profile untuk frontend
 * Semua role (HR, GA, Manager, Employee) dapat menggunakan endpoint ini
 */

class PersonalProfileAPI {
    constructor(baseUrl = '/api/personal') {
        this.baseUrl = baseUrl;
    }

    /**
     * Get personal profile data
     * @param {number} employeeId - Employee ID
     * @returns {Promise}
     */
    async getProfile(employeeId) {
        try {
            const response = await fetch(`${this.baseUrl}/profile?employee_id=${employeeId}`);
            const data = await response.json();
            
            if (data.success) {
                return {
                    success: true,
                    data: data.data
                };
            } else {
                return {
                    success: false,
                    message: data.message,
                    errors: data.errors || {}
                };
            }
        } catch (error) {
            return {
                success: false,
                message: 'Network error: ' + error.message
            };
        }
    }

    /**
     * Update personal profile
     * @param {number} employeeId - Employee ID
     * @param {Object} updateData - Data to update
     * @returns {Promise}
     */
    async updateProfile(employeeId, updateData) {
        try {
            const response = await fetch(`${this.baseUrl}/profile`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    employee_id: employeeId,
                    ...updateData
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                return {
                    success: true,
                    data: data.data
                };
            } else {
                return {
                    success: false,
                    message: data.message,
                    errors: data.errors || {}
                };
            }
        } catch (error) {
            return {
                success: false,
                message: 'Network error: ' + error.message
            };
        }
    }
}

// Vue.js Component Example
const PersonalProfileComponent = {
    template: `
        <div class="personal-profile">
            <!-- Loading State -->
            <div v-if="loading" class="loading">
                <div class="spinner"></div>
                <p>Loading profile data...</p>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="error">
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Profile</h3>
                    <p>{{ error }}</p>
                    <button @click="loadProfile" class="btn-retry">Retry</button>
                </div>
            </div>

            <!-- Profile Data -->
            <div v-else-if="profile" class="profile-content">
                <!-- Header Section -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <img v-if="profile.user_info?.profile_picture" 
                             :src="profile.user_info.profile_picture" 
                             :alt="profile.basic_info.nama_lengkap">
                        <div v-else class="avatar-placeholder">
                            {{ getInitials(profile.basic_info.nama_lengkap) }}
                        </div>
                    </div>
                    <div class="profile-info">
                        <h1>{{ profile.basic_info.nama_lengkap }}</h1>
                        <p class="position">{{ profile.basic_info.jabatan_saat_ini }}</p>
                        <p class="employee-id">Employee ID: {{ profile.basic_info.id }}</p>
                        <p class="nik">NIK: {{ profile.basic_info.nik }}</p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="statistics-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ profile.statistics.years_of_service }}</h3>
                            <p>Years of Service</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ profile.statistics.total_documents }}</h3>
                            <p>Documents</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ profile.statistics.total_trainings }}</h3>
                            <p>Trainings</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ profile.statistics.total_leave_requests }}</h3>
                            <p>Leave Requests</p>
                        </div>
                    </div>
                </div>

                <!-- Leave Quota Section -->
                <div v-if="profile.current_leave_quota" class="leave-quota-section">
                    <h2>Leave Quota ({{ profile.current_leave_quota.year }})</h2>
                    <div class="leave-quota-grid">
                        <div class="quota-card">
                            <h4>Annual Leave</h4>
                            <div class="quota-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" 
                                         :style="{ width: getQuotaPercentage(profile.current_leave_quota.annual_leave_used, profile.current_leave_quota.annual_leave_quota) + '%' }">
                                    </div>
                                </div>
                                <p>{{ profile.current_leave_quota.annual_leave_remaining }}/{{ profile.current_leave_quota.annual_leave_quota }}</p>
                            </div>
                        </div>
                        <div class="quota-card">
                            <h4>Sick Leave</h4>
                            <div class="quota-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" 
                                         :style="{ width: getQuotaPercentage(profile.current_leave_quota.sick_leave_used, profile.current_leave_quota.sick_leave_quota) + '%' }">
                                    </div>
                                </div>
                                <p>{{ profile.current_leave_quota.sick_leave_remaining }}/{{ profile.current_leave_quota.sick_leave_quota }}</p>
                            </div>
                        </div>
                        <div class="quota-card">
                            <h4>Emergency Leave</h4>
                            <div class="quota-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" 
                                         :style="{ width: getQuotaPercentage(profile.current_leave_quota.emergency_leave_used, profile.current_leave_quota.emergency_leave_quota) + '%' }">
                                    </div>
                                </div>
                                <p>{{ profile.current_leave_quota.emergency_leave_remaining }}/{{ profile.current_leave_quota.emergency_leave_quota }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Leave Requests -->
                <div v-if="profile.recent_leave_requests.length > 0" class="recent-leaves-section">
                    <h2>Recent Leave Requests</h2>
                    <div class="leave-requests-list">
                        <div v-for="leave in profile.recent_leave_requests" 
                             :key="leave.id" 
                             class="leave-request-item"
                             :class="getLeaveStatusClass(leave.overall_status)">
                            <div class="leave-info">
                                <h4>{{ formatLeaveType(leave.leave_type) }}</h4>
                                <p>{{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}</p>
                                <p class="reason">{{ leave.reason }}</p>
                            </div>
                            <div class="leave-status">
                                <span class="status-badge" :class="getLeaveStatusClass(leave.overall_status)">
                                    {{ leave.overall_status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="basic-info-section">
                    <h2>Basic Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name</label>
                            <p>{{ profile.basic_info.nama_lengkap }}</p>
                        </div>
                        <div class="info-item">
                            <label>Date of Birth</label>
                            <p>{{ formatDate(profile.basic_info.tanggal_lahir) }}</p>
                        </div>
                        <div class="info-item">
                            <label>Gender</label>
                            <p>{{ profile.basic_info.jenis_kelamin }}</p>
                        </div>
                        <div class="info-item">
                            <label>Marital Status</label>
                            <p>{{ profile.basic_info.status_pernikahan }}</p>
                        </div>
                        <div class="info-item">
                            <label>Address</label>
                            <p>{{ profile.basic_info.alamat }}</p>
                        </div>
                        <div class="info-item">
                            <label>Education Level</label>
                            <p>{{ profile.basic_info.tingkat_pendidikan }}</p>
                        </div>
                        <div class="info-item">
                            <label>Start Date</label>
                            <p>{{ formatDate(profile.basic_info.tanggal_mulai_kerja) }}</p>
                        </div>
                        <div class="info-item">
                            <label>Contract Number</label>
                            <p>{{ profile.basic_info.nomor_kontrak || 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div v-if="profile.user_info" class="contact-info-section">
                    <h2>Contact Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Email</label>
                            <p>{{ profile.user_info.email }}</p>
                        </div>
                        <div class="info-item">
                            <label>Phone</label>
                            <p>{{ profile.user_info.phone }}</p>
                        </div>
                        <div class="info-item">
                            <label>Role</label>
                            <p>{{ profile.user_info.role }}</p>
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div v-if="profile.documents.length > 0" class="documents-section">
                    <h2>Documents</h2>
                    <div class="documents-list">
                        <div v-for="doc in profile.documents" 
                             :key="doc.id" 
                             class="document-item">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h4>{{ doc.document_type }}</h4>
                                <p>Uploaded: {{ formatDate(doc.created_at) }}</p>
                            </div>
                            <div class="document-actions">
                                <button @click="downloadDocument(doc.file_path)" class="btn-download">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Button -->
                <div class="profile-actions">
                    <button @click="showEditModal = true" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>
    `,

    data() {
        return {
            loading: true,
            error: null,
            profile: null,
            showEditModal: false,
            api: new PersonalProfileAPI()
        };
    },

    async mounted() {
        await this.loadProfile();
    },

    methods: {
        async loadProfile() {
            this.loading = true;
            this.error = null;

            // Get employee_id from store or props
            const employeeId = this.$store.state.user.employee_id || this.$route.params.employeeId;

            if (!employeeId) {
                this.error = 'Employee ID not found';
                this.loading = false;
                return;
            }

            const result = await this.api.getProfile(employeeId);

            if (result.success) {
                this.profile = result.data;
            } else {
                this.error = result.message;
            }

            this.loading = false;
        },

        async updateProfile(updateData) {
            const employeeId = this.$store.state.user.employee_id;
            const result = await this.api.updateProfile(employeeId, updateData);

            if (result.success) {
                // Refresh profile data
                await this.loadProfile();
                this.showEditModal = false;
                this.$toast.success('Profile updated successfully');
            } else {
                this.$toast.error(result.message);
            }

            return result;
        },

        getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').toUpperCase();
        },

        getQuotaPercentage(used, total) {
            return total > 0 ? (used / total) * 100 : 0;
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('id-ID');
        },

        formatLeaveType(type) {
            const types = {
                'annual': 'Annual Leave',
                'sick': 'Sick Leave',
                'emergency': 'Emergency Leave',
                'maternity': 'Maternity Leave',
                'paternity': 'Paternity Leave',
                'marriage': 'Marriage Leave',
                'bereavement': 'Bereavement Leave'
            };
            return types[type] || type;
        },

        getLeaveStatusClass(status) {
            const classes = {
                'pending': 'status-pending',
                'approved': 'status-approved',
                'rejected': 'status-rejected',
                'cancelled': 'status-cancelled'
            };
            return classes[status] || 'status-pending';
        },

        downloadDocument(filePath) {
            // Implementation for document download
            window.open(filePath, '_blank');
        }
    }
};

// Usage in Vue app
const app = new Vue({
    el: '#app',
    components: {
        'personal-profile': PersonalProfileComponent
    },
    store: {
        state: {
            user: {
                employee_id: 8 // Get from login response
            }
        }
    }
});

// React Hook Example
function usePersonalProfile(employeeId) {
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const api = new PersonalProfileAPI();

    const loadProfile = useCallback(async () => {
        setLoading(true);
        setError(null);

        const result = await api.getProfile(employeeId);

        if (result.success) {
            setProfile(result.data);
        } else {
            setError(result.message);
        }

        setLoading(false);
    }, [employeeId]);

    const updateProfile = useCallback(async (updateData) => {
        const result = await api.updateProfile(employeeId, updateData);

        if (result.success) {
            await loadProfile();
            return result;
        } else {
            throw new Error(result.message);
        }
    }, [employeeId, loadProfile]);

    useEffect(() => {
        loadProfile();
    }, [loadProfile]);

    return {
        profile,
        loading,
        error,
        updateProfile,
        reload: loadProfile
    };
}

// Export for use in other files
export { PersonalProfileAPI, PersonalProfileComponent, usePersonalProfile }; 