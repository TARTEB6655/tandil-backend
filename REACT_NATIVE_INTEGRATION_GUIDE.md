# Complete React Native Integration Guide

This is a comprehensive, step-by-step guide to integrate your React Native app with the Laravel backend.

## 📋 Prerequisites

- React Native project set up
- Laravel backend running (local or production)
- Both on the same network (for development)

## 🔧 Step 1: Install Required Dependencies

In your React Native project, install the necessary packages:

```bash
npm install axios
npm install @react-native-async-storage/async-storage
# or
yarn add axios @react-native-async-storage/async-storage
```

## 📁 Step 2: Create API Configuration

Create `src/config/api.ts`:

```typescript
export const API_CONFIG = {
  // Development URLs
  BASE_URL: __DEV__ 
    ? 'http://10.0.2.2:8000'              // Android Emulator
    // ? 'http://localhost:8000'          // iOS Simulator
    // ? 'http://192.168.1.100:8000'      // Physical Device (replace with your computer's IP)
    : 'https://api.yourdomain.com',      // Production

  API_PREFIX: '/api',
  TIMEOUT: 30000,
};

// Helper to get full API URL
export const getApiUrl = (endpoint: string): string => {
  const baseUrl = API_CONFIG.BASE_URL.replace(/\/$/, '');
  const prefix = API_CONFIG.API_PREFIX.replace(/^\//, '').replace(/\/$/, '');
  const path = endpoint.replace(/^\//, '');
  return `${baseUrl}/${prefix}/${path}`;
};
```

## 🔌 Step 3: Create API Client

Create `src/services/apiClient.ts`:

```typescript
import axios, { AxiosInstance, AxiosError, AxiosRequestConfig } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_CONFIG } from '../config/api';

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}`,
      timeout: API_CONFIG.TIMEOUT,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors() {
    // Request interceptor - Add token to requests
    this.client.interceptors.request.use(
      async (config) => {
        const token = await AsyncStorage.getItem('auth_token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => {
        return Promise.reject(error);
      }
    );

    // Response interceptor - Handle errors
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        if (error.response?.status === 401) {
          // Token expired or invalid
          await AsyncStorage.removeItem('auth_token');
          await AsyncStorage.removeItem('user_data');
          // Navigate to login screen
          // navigationRef.navigate('Login');
        }
        return Promise.reject(error);
      }
    );
  }

  async get<T>(url: string, config?: AxiosRequestConfig) {
    const response = await this.client.get<T>(url, config);
    return response.data;
  }

  async post<T>(url: string, data?: any, config?: AxiosRequestConfig) {
    const response = await this.client.post<T>(url, data, config);
    return response.data;
  }

  async put<T>(url: string, data?: any, config?: AxiosRequestConfig) {
    const response = await this.client.put<T>(url, data, config);
    return response.data;
  }

  async delete<T>(url: string, config?: AxiosRequestConfig) {
    const response = await this.client.delete<T>(url, config);
    return response.data;
  }
}

export const apiClient = new ApiClient();
```

## 🔐 Step 4: Create Authentication Service

Create `src/services/authService.ts`:

```typescript
import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiClient } from './apiClient';

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
  role: 'client' | 'technician' | 'supervisor' | 'area_manager' | 'hr' | 'admin';
}

export interface AuthResponse {
  status: boolean;
  message: string;
  token: string;
  role: string;
  user: any;
  data: any;
}

export interface ApiResponse<T = any> {
  status: boolean;
  message: string;
  data?: T;
  errors?: Record<string, string[]>;
}

class AuthService {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const response = await apiClient.post<AuthResponse>('/auth/login', credentials);
      
      if (response.status && response.token) {
        // Store token and user data
        await AsyncStorage.setItem('auth_token', response.token);
        await AsyncStorage.setItem('user_data', JSON.stringify(response.user));
      }
      
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async register(data: RegisterData): Promise<AuthResponse> {
    try {
      const response = await apiClient.post<AuthResponse>('/auth/register', data);
      
      if (response.status && response.token) {
        // Store token and user data
        await AsyncStorage.setItem('auth_token', response.token);
        await AsyncStorage.setItem('user_data', JSON.stringify(response.user));
      }
      
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async logout(): Promise<void> {
    try {
      await apiClient.post('/auth/logout');
    } catch (error) {
      // Continue with logout even if API call fails
    } finally {
      // Clear local storage
      await AsyncStorage.removeItem('auth_token');
      await AsyncStorage.removeItem('user_data');
    }
  }

  async getCurrentUser(): Promise<any> {
    try {
      const response = await apiClient.get<ApiResponse>('/auth/user');
      return response.data;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getStoredToken(): Promise<string | null> {
    return await AsyncStorage.getItem('auth_token');
  }

  async getStoredUser(): Promise<any | null> {
    const userData = await AsyncStorage.getItem('user_data');
    return userData ? JSON.parse(userData) : null;
  }

  async isAuthenticated(): Promise<boolean> {
    const token = await this.getStoredToken();
    return !!token;
  }

  private handleError(error: any): Error {
    if (error.response?.data) {
      const data = error.response.data;
      if (data.errors) {
        // Validation errors
        const firstError = Object.values(data.errors)[0];
        return new Error(Array.isArray(firstError) ? firstError[0] : firstError);
      }
      return new Error(data.message || 'An error occurred');
    }
    return new Error(error.message || 'Network error');
  }
}

export const authService = new AuthService();
```

## 🛍️ Step 5: Create Product Service

Create `src/services/productService.ts`:

```typescript
import { apiClient } from './apiClient';
import { ApiResponse } from './authService';

export interface Product {
  id: number;
  name: string;
  description: string;
  price: number;
  image?: string;
  category_id?: number;
  // Add other product fields
}

export interface ProductListParams {
  search?: string;
  category_id?: number;
  per_page?: number;
  sort_by?: 'name' | 'price' | 'created_at';
  sort_dir?: 'asc' | 'desc';
}

class ProductService {
  async getProducts(params?: ProductListParams): Promise<ApiResponse<Product[]>> {
    try {
      const response = await apiClient.get<ApiResponse<Product[]>>('/products', { params });
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getProduct(id: number): Promise<ApiResponse<Product>> {
    try {
      const response = await apiClient.get<ApiResponse<Product>>(`/products/${id}`);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async searchProducts(query: string): Promise<ApiResponse<Product[]>> {
    try {
      const response = await apiClient.get<ApiResponse<Product[]>>('/products/search', {
        params: { q: query },
      });
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getCategories(): Promise<ApiResponse<any[]>> {
    try {
      const response = await apiClient.get<ApiResponse<any[]>>('/products/categories');
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getProductsByCategory(categoryId: number): Promise<ApiResponse<Product[]>> {
    try {
      const response = await apiClient.get<ApiResponse<Product[]>>(
        `/products/category/${categoryId}`
      );
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  private handleError(error: any): Error {
    if (error.response?.data) {
      const data = error.response.data;
      return new Error(data.message || 'An error occurred');
    }
    return new Error(error.message || 'Network error');
  }
}

export const productService = new ProductService();
```

## 📦 Step 6: Create Order Service

Create `src/services/orderService.ts`:

```typescript
import { apiClient } from './apiClient';
import { ApiResponse } from './authService';

export interface Order {
  id: number;
  user_id: number;
  total_amount: number;
  order_status: string;
  payment_status: string;
  items?: OrderItem[];
  created_at: string;
}

export interface OrderItem {
  id: number;
  product_id: number;
  quantity: number;
  price: number;
  subtotal: number;
  product?: any;
}

export interface CreateOrderData {
  items: Array<{ product_id: number; qty: number }>;
  total_amount: number;
  currency?: string;
}

class OrderService {
  async getOrders(): Promise<ApiResponse<Order[]>> {
    try {
      const response = await apiClient.get<ApiResponse<Order[]>>('/orders');
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getOrder(id: number): Promise<ApiResponse<Order>> {
    try {
      const response = await apiClient.get<ApiResponse<Order>>(`/orders/${id}`);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async createOrder(data: CreateOrderData): Promise<ApiResponse<Order>> {
    try {
      const response = await apiClient.post<ApiResponse<Order>>('/orders', data);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async updateOrder(id: number, data: Partial<Order>): Promise<ApiResponse<Order>> {
    try {
      const response = await apiClient.put<ApiResponse<Order>>(`/orders/${id}`, data);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async cancelOrder(id: number): Promise<ApiResponse<Order>> {
    try {
      const response = await apiClient.post<ApiResponse<Order>>(`/orders/${id}/cancel`);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async trackOrder(id: number): Promise<ApiResponse<any>> {
    try {
      const response = await apiClient.get<ApiResponse<any>>(`/orders/${id}/track`);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async rateOrder(id: number, rating: number, comment?: string): Promise<ApiResponse<any>> {
    try {
      const response = await apiClient.post<ApiResponse<any>>(`/orders/${id}/rate`, {
        rating,
        comment,
      });
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  private handleError(error: any): Error {
    if (error.response?.data) {
      const data = error.response.data;
      return new Error(data.message || 'An error occurred');
    }
    return new Error(error.message || 'Network error');
  }
}

export const orderService = new OrderService();
```

## 👤 Step 7: Create User Service

Create `src/services/userService.ts`:

```typescript
import { apiClient } from './apiClient';
import { ApiResponse } from './authService';

export interface UserProfile {
  id: number;
  name: string;
  email: string;
  phone?: string;
  role: string;
}

class UserService {
  async getProfile(): Promise<ApiResponse<UserProfile>> {
    try {
      const response = await apiClient.get<ApiResponse<UserProfile>>('/user/profile');
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async updateProfile(data: Partial<UserProfile>): Promise<ApiResponse<UserProfile>> {
    try {
      const response = await apiClient.put<ApiResponse<UserProfile>>('/user/profile', data);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getNotifications(): Promise<ApiResponse<any[]>> {
    try {
      const response = await apiClient.get<ApiResponse<any[]>>('/user/notifications');
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async markNotificationAsRead(id: string): Promise<ApiResponse<void>> {
    try {
      const response = await apiClient.post<ApiResponse<void>>(`/user/notifications/${id}/read`);
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async markAllNotificationsAsRead(): Promise<ApiResponse<void>> {
    try {
      const response = await apiClient.post<ApiResponse<void>>('/user/notifications/read-all');
      return response;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  private handleError(error: any): Error {
    if (error.response?.data) {
      const data = error.response.data;
      return new Error(data.message || 'An error occurred');
    }
    return new Error(error.message || 'Network error');
  }
}

export const userService = new UserService();
```

## 📱 Step 8: Create Login Screen Example

Create `src/screens/LoginScreen.tsx`:

```typescript
import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { authService } from '../services/authService';

export default function LoginScreen({ navigation }: any) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!email || !password) {
      Alert.alert('Error', 'Please fill in all fields');
      return;
    }

    setLoading(true);
    try {
      const response = await authService.login({ email, password });
      
      if (response.status) {
        // Navigate to home screen
        navigation.replace('Home');
      } else {
        Alert.alert('Error', response.message || 'Login failed');
      }
    } catch (error: any) {
      Alert.alert('Error', error.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Login</Text>
      
      <TextInput
        style={styles.input}
        placeholder="Email"
        value={email}
        onChangeText={setEmail}
        keyboardType="email-address"
        autoCapitalize="none"
      />
      
      <TextInput
        style={styles.input}
        placeholder="Password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      
      <TouchableOpacity
        style={styles.button}
        onPress={handleLogin}
        disabled={loading}
      >
        {loading ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>Login</Text>
        )}
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 20,
    justifyContent: 'center',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 30,
    textAlign: 'center',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    padding: 15,
    marginBottom: 15,
    borderRadius: 5,
  },
  button: {
    backgroundColor: '#007AFF',
    padding: 15,
    borderRadius: 5,
    alignItems: 'center',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
```

## 🛍️ Step 9: Create Products Screen Example

Create `src/screens/ProductsScreen.tsx`:

```typescript
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { productService } from '../services/productService';
import { Product } from '../services/productService';

export default function ProductsScreen() {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    loadProducts();
  }, []);

  const loadProducts = async () => {
    try {
      setLoading(true);
      const response = await productService.getProducts();
      if (response.status && response.data) {
        setProducts(response.data);
      }
    } catch (error: any) {
      console.error('Error loading products:', error);
    } finally {
      setLoading(false);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await loadProducts();
    setRefreshing(false);
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={products}
        keyExtractor={(item) => item.id.toString()}
        renderItem={({ item }) => (
          <View style={styles.productCard}>
            <Text style={styles.productName}>{item.name}</Text>
            <Text style={styles.productPrice}>${item.price}</Text>
            <Text style={styles.productDescription}>{item.description}</Text>
          </View>
        )}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 10,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  productCard: {
    backgroundColor: '#fff',
    padding: 15,
    marginBottom: 10,
    borderRadius: 5,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  productName: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 5,
  },
  productPrice: {
    fontSize: 16,
    color: '#007AFF',
    marginBottom: 5,
  },
  productDescription: {
    fontSize: 14,
    color: '#666',
  },
});
```

## 🔍 Step 10: Find Your Computer's IP Address

For testing on a physical device, you need your computer's IP address:

**Windows:**
```bash
ipconfig
# Look for IPv4 Address under your active network adapter
```

**Mac/Linux:**
```bash
ifconfig
# or
ip addr show
```

Update `API_CONFIG.BASE_URL` with your IP (e.g., `http://192.168.1.100:8000`)

## ✅ Step 11: Test the Integration

### Test Health Endpoint:
```typescript
// In your app
import { apiClient } from './services/apiClient';

const testConnection = async () => {
  try {
    const response = await apiClient.get('/health');
    console.log('API is working:', response);
  } catch (error) {
    console.error('Connection failed:', error);
  }
};
```

### Test Authentication:
```typescript
// Test login
const testLogin = async () => {
  try {
    const response = await authService.login({
      email: 'user@example.com',
      password: 'password123',
    });
    console.log('Login successful:', response);
  } catch (error) {
    console.error('Login failed:', error);
  }
};
```

## 🐛 Troubleshooting

### Network Error
- **Android Emulator**: Use `http://10.0.2.2:8000`
- **iOS Simulator**: Use `http://localhost:8000`
- **Physical Device**: Use your computer's IP address
- Ensure both devices are on the same WiFi network
- Check if Laravel is running: `php artisan serve`

### 401 Unauthorized
- Check if token is being stored correctly
- Verify token format: `Bearer {token}`
- Check if user account is active

### CORS Issues
- CORS is already configured in the backend
- React Native doesn't have CORS restrictions (it's not a browser)
- If you see CORS errors, check your API configuration

## 📝 Complete File Structure

```
src/
├── config/
│   └── api.ts
├── services/
│   ├── apiClient.ts
│   ├── authService.ts
│   ├── productService.ts
│   ├── orderService.ts
│   └── userService.ts
└── screens/
    ├── LoginScreen.tsx
    └── ProductsScreen.tsx
```

## 🎯 Next Steps

1. ✅ Set up API configuration
2. ✅ Create API client
3. ✅ Implement authentication
4. ✅ Create service files
5. ✅ Build your screens
6. ✅ Test on emulator
7. ✅ Test on physical device
8. ✅ Deploy to production

---

**Need Help?** Check the Laravel logs at `storage/logs/laravel.log` for detailed error messages.

