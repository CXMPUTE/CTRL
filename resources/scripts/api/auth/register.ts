import http from '@/api/http';

export interface SignupResponse {
    complete: boolean;
    intended: string;
}

export interface SignupData {
    email: string;
    username: string;
    password: string;
    recaptchaData?: string | null;
}

export default ({ email, username, password, recaptchaData }: SignupData): Promise<SignupResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/sanctum/csrf-cookie')
            .then(() =>
                http.post('/auth/signup', {
                    email,
                    username,
                    password,
                    'g-recaptcha-response': recaptchaData,
                })
            )
            .then((response) => {
                if (!(response.data instanceof Object)) {
                    return reject(new Error('An error occurred while processing the login request.'));
                }

                return resolve({
                    complete: response.data.data.complete,
                    intended: response.data.data.intended,
                });
            })
            .catch(reject);
    });
};
